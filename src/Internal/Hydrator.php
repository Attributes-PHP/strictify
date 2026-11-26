<?php

declare(strict_types=1);

namespace Attributes\Strictify\Internal;

use Attributes\Strictify\BaseModel;
use Attributes\Strictify\Exceptions\InvalidBaseModelException;
use Attributes\Strictify\Exceptions\ValidationException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Hydrates a BaseModel instance from a raw data array.
 *
 * @internal
 */
final class Hydrator
{
    /**
     * The prepared property entries of each model class hierarchy per
     * alias generator: the property reflection, its resolved field name,
     * whether it declares a default value, whether it is #[Sensitive], the
     * compiled type plan and the instantiated constraints, all static per
     * class, so the walk and the field-name resolution run once per class.
     *
     * @var array<string, list<array{property: ReflectionProperty, name: string, fieldName: string, hasDefault: bool, sensitive: bool, plan: TypePlan, constraints: list<object>}>>
     */
    private static array $entriesCache = [];

    /**
     * Whether each model class overrides one of the validation hooks.
     *
     * @var array<string, bool>
     */
    private static array $hooksCache = [];

    /**
     * Validates a raw data array against the model's type hints and
     * attributes, populates the model and runs its hooks.
     *
     * @throws ValidationException when any field fails validation
     */
    public static function hydrate(array $rawData, BaseModel $model): BaseModel
    {
        $class = $model::class;
        $configs = ResolvedConfigs::resolve($class);
        $hooksOverridden = self::overridesHooks($class);

        if ($hooksOverridden) {
            $result = (new ReflectionMethod($model, 'beforeValidation'))
                ->invoke($model, $rawData, $configs->toModelConfigs());

            if (is_array($result)) {
                $rawData = $result;
            }
        }

        $errors = [];

        if (! self::validateModel($rawData, $model, $configs, $errors, null)) {
            throw new ValidationException($errors);
        }

        if ($hooksOverridden) {
            (new ReflectionMethod($model, 'afterValidation'))
                ->invoke($model, $rawData, $configs->toModelConfigs());
        }

        return $model;
    }

    /**
     * Validates the properties of the model class hierarchy against the
     * raw data. Returns true only when the shared error collection stayed
     * empty.
     */
    public static function validateModel(
        array $rawData,
        BaseModel $model,
        ResolvedConfigs $configs,
        array &$errors,
        ?string $parentPath,
    ): bool {
        // Nested models re-enter here; past the depth limit the run stops
        // with a catchable exception instead of exhausting the stack
        DepthGuard::enter();

        try {
            return self::validateModelBody($rawData, $model, $configs, $errors, $parentPath);
        } finally {
            DepthGuard::leave();
        }
    }

    private static function validateModelBody(
        array $rawData,
        BaseModel $model,
        ResolvedConfigs $configs,
        array &$errors,
        ?string $parentPath,
    ): bool {
        $cacheKey = $model::class."\0".(string) $configs->aliasGenerator;

        foreach (self::entriesFor($cacheKey, $model::class, $configs->aliasGenerator) as $entry) {
            $fieldName = $entry['fieldName'];

            if (! array_key_exists($fieldName, $rawData)) {
                if ($entry['hasDefault']) {
                    continue;
                }

                ErrorMessages::addFieldError(ErrorMessages::REQUIRED, $parentPath, $fieldName, $entry['property'], null, $errors);

                if ($configs->stopAtFirstError) {
                    return false;
                }

                continue;
            }

            $value = $rawData[$fieldName];

            // String normalizations run before the type check, so
            // constraints and the written property see the normalized
            // value. Strings bound for non-string hints (e.g. DateTime
            // coercion) are left untouched.
            if (is_string($value)
                && ($configs->strToLower || $configs->strToUpper || $configs->stripWhitespace)
                && ($entry['plan']->untyped || $entry['plan']->mixed || ($entry['plan']->basicMask & Masks::STRING) !== 0)) {
                $value = self::transformString($value, $configs);
            }

            $result = TypeHintValidator::validate($value, $entry['property'], $configs, $errors, $parentPath, $fieldName, $entry['plan']);

            if (! $result['ok']) {
                if ($configs->stopAtFirstError) {
                    return false;
                }

                continue;
            }

            $value = $result['value'];

            // Constraints run on the (possibly coerced) value; a failing
            // constraint keeps the value out of the model. Every declared
            // constraint reports its own error unless stopAtFirstError
            // aborts the run.
            $constraintsValid = true;

            foreach ($entry['constraints'] as $constraint) {
                if (! Constraints::run($constraint, $value, $parentPath, $fieldName, $entry['property'], $configs, $errors)) {
                    $constraintsValid = false;

                    if ($configs->stopAtFirstError) {
                        return false;
                    }
                }
            }

            if (! $constraintsValid) {
                continue;
            }

            // Virtual (get-only) hooked properties have no backing store
            // and cannot be written; the standard write path runs the set
            // hook of hooked properties
            if (self::isVirtual($entry['property'])) {
                continue;
            }

            $model->{$entry['name']} = $value;
        }

        self::checkExtraFields($rawData, $model, $configs, $parentPath, $errors);

        return $errors === [];
    }

    /**
     * The prepared property entries of the model class hierarchy,
     * cached per class and alias generator.
     *
     * @return list<array{property: ReflectionProperty, name: string, fieldName: string, hasDefault: bool, sensitive: bool, plan: TypePlan, constraints: list<object>}>
     */
    public static function entriesFor(string $cacheKey, string $class, ?string $generator): array
    {
        if (isset(self::$entriesCache[$cacheKey])) {
            return self::$entriesCache[$cacheKey];
        }

        $entries = [];
        $seenFieldNames = [];

        for ($current = $class; $current !== false && $current !== BaseModel::class; $current = get_parent_class($current)) {
            $reflection = new ReflectionClass($current);

            foreach ($reflection->getProperties() as $property) {
                if ($property->isStatic() || $property->isPrivate() || $property->isProtected()) {
                    continue;
                }

                if ($property->getDeclaringClass()->getName() !== $current) {
                    continue;
                }

                $plan = TypeHintValidator::plan($property);

                Constraints::checkPlacement($property, $plan);

                $fieldName = FieldNameResolver::resolve($property, $generator);

                // Two properties resolving to the same input key would both
                // be hydrated from that one key (and one of them would keep
                // its default), which is never what the model means: an
                // #[Alias] naming another property, or an alias generator
                // mapping two names to the same key
                if (isset($seenFieldNames[$fieldName])) {
                    $sibling = $seenFieldNames[$fieldName];

                    throw new InvalidBaseModelException(sprintf(
                        'Properties %s::$%s and %s::$%s both resolve to the input key "%s".',
                        $sibling['class'],
                        $sibling['name'],
                        $property->getDeclaringClass()->getName(),
                        $property->getName(),
                        $fieldName
                    ));
                }

                $seenFieldNames[$fieldName] = ['class' => $property->getDeclaringClass()->getName(), 'name' => $property->getName()];

                $entries[] = [
                    'property' => $property,
                    'name' => $property->getName(),
                    'fieldName' => $fieldName,
                    'hasDefault' => $property->hasDefaultValue(),
                    'sensitive' => ErrorMessages::isSensitive($property),
                    'plan' => $plan,
                    'constraints' => Constraints::of($property),
                ];
            }
        }

        return self::$entriesCache[$cacheKey] = $entries;
    }

    /**
     * ModelConfigs extra: 'forbid' reports every input key that resolves to
     * no property of the model; 'allow' stores unknown keys as dynamic
     * properties; 'ignore' drops them.
     */
    private static function checkExtraFields(
        array $rawData,
        BaseModel $model,
        ResolvedConfigs $configs,
        ?string $parentPath,
        array &$errors,
    ): void {
        if ($configs->extra !== 'forbid' && $configs->extra !== 'allow') {
            return;
        }

        $cacheKey = $model::class."\0".(string) $configs->aliasGenerator;
        $known = [];

        foreach (self::entriesFor($cacheKey, $model::class, $configs->aliasGenerator) as $entry) {
            $known[] = $entry['fieldName'];
        }

        foreach ($rawData as $key => $entry) {
            if (is_string($key) && in_array($key, $known, true)) {
                continue;
            }

            if ($configs->extra === 'allow') {
                // Integer keys have no property name, and a key naming a
                // declared property (reachable when an alias generator
                // renamed the expected key) is not a dynamic property
                if (! is_string($key) || property_exists($model, $key)) {
                    continue;
                }

                $model->{$key} = $entry;

                continue;
            }

            ErrorMessages::addExtraError($parentPath, (string) $key, $entry, $errors);

            if ($configs->stopAtFirstError) {
                return;
            }
        }
    }

    /**
     * Applies the ModelConfigs string normalizations (stripWhitespace,
     * strToLower, strToUpper) in that order, so strToUpper wins when both
     * case flags are set.
     */
    private static function transformString(string $value, ResolvedConfigs $configs): string
    {
        if ($configs->stripWhitespace) {
            $value = trim($value, " \t\n\r\0\x0B");
        }

        if ($configs->strToLower) {
            $value = strtolower($value);
        }

        if ($configs->strToUpper) {
            $value = strtoupper($value);
        }

        return $value;
    }

    /**
     * Whether the property is a virtual (get-only) hooked property, which
     * has no backing store to write to. Property hooks are PHP 8.4+.
     */
    private static function isVirtual(ReflectionProperty $property): bool
    {
        return PHP_VERSION_ID >= 80400 && $property->isVirtual();
    }

    /**
     * Whether the model class overrides one of the validation hooks,
     * cached per class.
     */
    private static function overridesHooks(string $class): bool
    {
        if (array_key_exists($class, self::$hooksCache)) {
            return self::$hooksCache[$class];
        }

        $reflection = new ReflectionClass($class);
        $overridden = false;

        foreach (['beforeValidation', 'afterValidation'] as $method) {
            $hook = $reflection->getMethod($method);

            if (! $hook instanceof ReflectionMethod || $hook->getDeclaringClass()->getName() !== BaseModel::class) {
                $overridden = true;

                break;
            }
        }

        return self::$hooksCache[$class] = $overridden;
    }
}
