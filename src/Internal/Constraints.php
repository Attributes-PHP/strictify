<?php

declare(strict_types=1);

namespace Attributes\Strictify\Internal;

use Attributes\Strictify\Exceptions\InvalidBaseModelException;
use Attributes\Strictify\Fields\ConfigAwareConstraint;
use Attributes\Strictify\Fields\Constraint;
use Attributes\Strictify\Fields\Constraints\AlphaDash;
use Attributes\Strictify\Fields\Constraints\Ascii;
use Attributes\Strictify\Fields\Constraints\Between;
use Attributes\Strictify\Fields\Constraints\Contains;
use Attributes\Strictify\Fields\Constraints\DateTime;
use Attributes\Strictify\Fields\Constraints\EndsWith;
use Attributes\Strictify\Fields\Constraints\In;
use Attributes\Strictify\Fields\Constraints\Max;
use Attributes\Strictify\Fields\Constraints\Min;
use Attributes\Strictify\Fields\Constraints\MultipleOf;
use Attributes\Strictify\Fields\Constraints\Regex;
use Attributes\Strictify\Fields\Constraints\Size;
use Attributes\Strictify\Fields\Constraints\StartsWith;
use Attributes\Strictify\Fields\Constraints\Ulid;
use Attributes\Strictify\Fields\Constraints\Uuid;
use Attributes\Strictify\Fields\Dict;
use Attributes\Strictify\Fields\Intersection;
use Attributes\Strictify\Fields\Sequence;
use Attributes\Strictify\Fields\Union;
use Attributes\Strictify\Type;
use ReflectionAttribute;
use ReflectionProperty;

/**
 * Collects, compiles and runs the constraint attributes of a model
 * property: the built-in constraints, a Union declared directly on the
 * property (a whole-value constraint) and userland attributes implementing
 * the Constraint interface.
 *
 * @internal
 */
final class Constraints
{
    /**
     * The built-in constraint classes and their error types.
     */
    private const BUILTIN = [
        AlphaDash::class => ErrorMessages::ALPHA_DASH,
        Ascii::class => ErrorMessages::ASCII,
        Between::class => ErrorMessages::BETWEEN,
        Contains::class => ErrorMessages::CONTAINS,
        DateTime::class => ErrorMessages::DATETIME,
        EndsWith::class => ErrorMessages::ENDS_WITH,
        In::class => ErrorMessages::IN,
        Max::class => ErrorMessages::MAX,
        Min::class => ErrorMessages::MIN,
        MultipleOf::class => ErrorMessages::MULTIPLE_OF,
        Regex::class => ErrorMessages::REGEX,
        Size::class => ErrorMessages::SIZE,
        StartsWith::class => ErrorMessages::STARTS_WITH,
        Ulid::class => ErrorMessages::ULID,
        Uuid::class => ErrorMessages::UUID,
    ];

    /**
     * The instantiated constraint attributes of each property, in
     * declaration order, cached per property.
     *
     * @var array<string, list<object>>
     */
    private static array $constraintCache = [];

    /**
     * The #[DateTime] constraint of each property, cached per property.
     *
     * @var array<string, DateTime|null>
     */
    private static array $dateTimeCache = [];

    /**
     * The constraint attributes of a property, instantiated once.
     *
     * @return list<object>
     */
    public static function of(ReflectionProperty $property): array
    {
        $cacheKey = $property->getDeclaringClass()->getName().'::$'.$property->getName();

        if (array_key_exists($cacheKey, self::$constraintCache)) {
            return self::$constraintCache[$cacheKey];
        }

        $constraints = [];

        foreach ($property->getAttributes() as $attribute) {
            $name = $attribute->getName();

            if (isset(self::BUILTIN[$name]) || $name === Union::class) {
                $constraints[] = $attribute->newInstance();

                continue;
            }

            $class = class_exists($name) ? $name : null;

            if ($class !== null && is_subclass_of($class, Constraint::class)) {
                $constraints[] = self::instantiateUserland($class, $attribute);
            }
        }

        return self::$constraintCache[$cacheKey] = $constraints;
    }

    /**
     * Instantiates a userland constraint by evaluating its arguments and
     * calling its constructor directly, like the engine does for internal
     * attribute classes: the class does not need the #[Attribute] marker.
     */
    private static function instantiateUserland(string $class, ReflectionAttribute $attribute): object
    {
        $reflection = new \ReflectionClass($class);
        $instance = $reflection->newInstanceWithoutConstructor();
        $constructor = $reflection->getConstructor();

        if ($constructor !== null) {
            $constructor->invokeArgs($instance, $attribute->getArguments());
        }

        return $instance;
    }

    /**
     * The #[DateTime] constraint declared on a property, when any.
     */
    public static function dateTimeOf(ReflectionProperty $property): ?DateTime
    {
        $cacheKey = $property->getDeclaringClass()->getName().'::$'.$property->getName();

        if (array_key_exists($cacheKey, self::$dateTimeCache)) {
            return self::$dateTimeCache[$cacheKey];
        }

        $attributes = $property->getAttributes(DateTime::class);

        return self::$dateTimeCache[$cacheKey] = $attributes === [] ? null : $attributes[0]->newInstance();
    }

    /**
     * Runs the placement and consistency rules of the array type-hint and
     * Union attributes once per property. A failure keeps firing on every
     * validation.
     */
    public static function checkPlacement(ReflectionProperty $property, TypePlan $plan): void
    {
        $arrayLike = $plan->untyped || $plan->mixed || ($plan->basicMask & Masks::ARRAY) !== 0;
        $arrayOnly = ! $plan->untyped && ! $plan->mixed
            && ($plan->basicMask & ~Masks::NULL) === Masks::ARRAY
            && $plan->classes === [] && $plan->intersections === [];

        foreach ($property->getAttributes() as $attribute) {
            $name = $attribute->getName();

            if (($name === Sequence::class || $name === Dict::class) && ! $arrayLike) {
                throw new InvalidBaseModelException(sprintf(
                    'Sequence/Dict can only be declared on array-like properties (array, a union containing array, mixed or untyped); property %s::$%s is not array-like.',
                    $property->getDeclaringClass()->getName(),
                    $property->getName()
                ));
            }

            if ($name === Union::class && $arrayOnly) {
                throw new InvalidBaseModelException(sprintf(
                    'Union cannot be declared directly on an array property %s::$%s; use Sequence(of: new Union(...)) or Dict(of: ...) instead.',
                    $property->getDeclaringClass()->getName(),
                    $property->getName()
                ));
            }
        }

        if ($plan->untyped || $plan->mixed) {
            return;
        }

        $requiresMatch = $plan->nullable || $plan->isUnion;

        if (! $requiresMatch) {
            return;
        }

        foreach ($property->getAttributes(Union::class) as $attribute) {
            self::checkUnionMatchesHint($attribute->newInstance(), $property, $plan);
        }
    }

    /**
     * Runs one constraint against a value. Returns true when the value
     * passes; on failure the error is added to the collection.
     */
    public static function run(
        object $constraint,
        mixed $value,
        ?string $parentPath,
        string $fieldName,
        ReflectionProperty $property,
        ResolvedConfigs $configs,
        array &$errors,
    ): bool {
        if ($constraint instanceof Union) {
            return self::runUnionScalar($constraint, $value, $parentPath, $fieldName, $property, $errors);
        }

        if ($constraint instanceof Constraint) {
            if ($constraint instanceof ConfigAwareConstraint) {
                $constraint->setModelConfigs($configs->toModelConfigs());
            }

            if ($constraint->validate($value)) {
                return true;
            }

            ErrorMessages::addCustomMessage($constraint->message(), $parentPath, $fieldName, $property, $value, $errors);

            return false;
        }

        return self::runBuiltin($constraint, $value, $parentPath, $fieldName, $property, $errors);
    }

    /**
     * Runs one of the built-in constraints.
     */
    private static function runBuiltin(
        object $constraint,
        mixed $value,
        ?string $parentPath,
        string $fieldName,
        ReflectionProperty $property,
        array &$errors,
    ): bool {
        switch ($constraint::class) {
            case AlphaDash::class:
                if (! is_string($value)) {
                    ErrorMessages::addFieldError(ErrorMessages::ALPHA_DASH_TYPE, $parentPath, $fieldName, $property, $value, $errors);

                    return false;
                }

                if (strspn($value, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_') !== strlen($value)) {
                    ErrorMessages::addFieldError(ErrorMessages::ALPHA_DASH, $parentPath, $fieldName, $property, $value, $errors);

                    return false;
                }

                return true;

            case Ascii::class:
                if (! is_string($value)) {
                    ErrorMessages::addFieldError(ErrorMessages::ASCII_TYPE, $parentPath, $fieldName, $property, $value, $errors);

                    return false;
                }

                if (preg_match('/[\x80-\xFF]/', $value) === 1) {
                    ErrorMessages::addFieldError(ErrorMessages::ASCII, $parentPath, $fieldName, $property, $value, $errors);

                    return false;
                }

                return true;

            case Between::class:
                $isNumber = is_int($value) || is_float($value);

                if ($isNumber && $value >= $constraint->min && $value <= $constraint->max) {
                    return true;
                }

                ErrorMessages::addFieldError(
                    $isNumber ? ErrorMessages::BETWEEN : ErrorMessages::BETWEEN_TYPE,
                    $parentPath,
                    $fieldName,
                    $property,
                    $value,
                    $errors,
                    Value::toString($constraint->min).' and '.Value::toString($constraint->max)
                );

                return false;

            case Contains::class:
                if (is_string($value)) {
                    foreach ($constraint->values as $needle) {
                        if (! str_contains($value, (string) $needle)) {
                            ErrorMessages::addFieldError(ErrorMessages::CONTAINS, $parentPath, $fieldName, $property, $value, $errors, self::valuesList($constraint->values));

                            return false;
                        }
                    }

                    return true;
                }

                if (is_array($value)) {
                    foreach ($constraint->values as $needle) {
                        $found = false;

                        foreach ($value as $element) {
                            if ($needle === $element) {
                                $found = true;

                                break;
                            }
                        }

                        if (! $found) {
                            ErrorMessages::addFieldError(ErrorMessages::CONTAINS, $parentPath, $fieldName, $property, $value, $errors, self::valuesList($constraint->values));

                            return false;
                        }
                    }

                    return true;
                }

                ErrorMessages::addFieldError(ErrorMessages::CONTAINS, $parentPath, $fieldName, $property, $value, $errors, self::valuesList($constraint->values));

                return false;

            case DateTime::class:
                if ($value instanceof \DateTimeInterface) {
                    return true;
                }

                if (is_string($value)) {
                    $parsed = $constraint->getTimezoneObj() === null
                        ? date_create_from_format($constraint->format, $value)
                        : date_create_from_format($constraint->format, $value, $constraint->getTimezoneObj());

                    if ($parsed !== false) {
                        return true;
                    }
                }

                ErrorMessages::addFieldError(ErrorMessages::DATETIME, $parentPath, $fieldName, $property, $value, $errors, Value::toString($constraint->format));

                return false;

            case EndsWith::class:
                if (is_string($value)) {
                    foreach ($constraint->values as $suffix) {
                        $suffixString = (string) $suffix;
                        $length = strlen($suffixString);

                        if ($length <= strlen($value) && substr_compare($value, $suffixString, -$length) === 0) {
                            return true;
                        }
                    }
                }

                ErrorMessages::addFieldError(ErrorMessages::ENDS_WITH, $parentPath, $fieldName, $property, $value, $errors, self::valuesList($constraint->values));

                return false;

            case In::class:
                foreach ($constraint->values as $allowed) {
                    if ($allowed === $value) {
                        return true;
                    }
                }

                ErrorMessages::addFieldError(ErrorMessages::IN, $parentPath, $fieldName, $property, $value, $errors, self::valuesList($constraint->values));

                return false;

            case Max::class:
                if ((is_int($value) || is_float($value)) && $value <= $constraint->value) {
                    return true;
                }

                ErrorMessages::addFieldError(ErrorMessages::MAX, $parentPath, $fieldName, $property, $value, $errors, Value::toString($constraint->value));

                return false;

            case Min::class:
                if ((is_int($value) || is_float($value)) && $value >= $constraint->value) {
                    return true;
                }

                ErrorMessages::addFieldError(ErrorMessages::MIN, $parentPath, $fieldName, $property, $value, $errors, Value::toString($constraint->value));

                return false;

            case MultipleOf::class:
                if (! is_int($value) && ! is_float($value)) {
                    ErrorMessages::addFieldError(ErrorMessages::MULTIPLE_OF, $parentPath, $fieldName, $property, $value, $errors, Value::toString($constraint->value));

                    return false;
                }

                $bound = $constraint->value;

                if (is_int($value) && is_int($bound)) {
                    if ($bound === 0 ? $value !== 0 : $value % $bound !== 0) {
                        ErrorMessages::addFieldError(ErrorMessages::MULTIPLE_OF, $parentPath, $fieldName, $property, $value, $errors, Value::toString($bound));

                        return false;
                    }

                    return true;
                }

                $number = (float) $value;
                $divisor = (float) $bound;

                if ($divisor == 0.0 && $number != 0.0) {
                    ErrorMessages::addFieldError(ErrorMessages::MULTIPLE_OF, $parentPath, $fieldName, $property, $value, $errors, Value::toString($bound));

                    return false;
                }

                $remainder = fmod($number, $divisor);
                $tolerance = abs($divisor) * 1e-9;
                $multiple = abs($remainder) <= $tolerance || abs(abs($remainder) - abs($divisor)) <= $tolerance;

                if (! $multiple) {
                    ErrorMessages::addFieldError(ErrorMessages::MULTIPLE_OF, $parentPath, $fieldName, $property, $value, $errors, Value::toString($bound));

                    return false;
                }

                return true;

            case Regex::class:
                if (is_string($value) && @preg_match($constraint->regex, $value) === 1) {
                    return true;
                }

                ErrorMessages::addFieldError(ErrorMessages::REGEX, $parentPath, $fieldName, $property, $value, $errors, Value::toString($constraint->regex));

                return false;

            case Size::class:
                $hasMin = $constraint->min !== null;
                $hasMax = $constraint->max !== null;

                if (! $hasMin && ! $hasMax) {
                    return true;
                }

                if (is_string($value)) {
                    $size = strlen($value);
                } elseif (is_array($value)) {
                    $size = count($value);
                } else {
                    ErrorMessages::addFieldError(ErrorMessages::SIZE, $parentPath, $fieldName, $property, $value, $errors, self::sizeExpected($hasMin, $hasMax, $constraint->min, $constraint->max));

                    return false;
                }

                if ($hasMin && $size < $constraint->min) {
                    ErrorMessages::addFieldError(ErrorMessages::SIZE, $parentPath, $fieldName, $property, $value, $errors, self::sizeExpected($hasMin, $hasMax, $constraint->min, $constraint->max));

                    return false;
                }

                if ($hasMax && $size > $constraint->max) {
                    ErrorMessages::addFieldError(ErrorMessages::SIZE, $parentPath, $fieldName, $property, $value, $errors, self::sizeExpected($hasMin, $hasMax, $constraint->min, $constraint->max));

                    return false;
                }

                return true;

            case StartsWith::class:
                if (is_string($value)) {
                    foreach ($constraint->values as $prefix) {
                        $prefixString = (string) $prefix;

                        if (strlen($prefixString) <= strlen($value) && substr_compare($value, $prefixString, 0, strlen($prefixString)) === 0) {
                            return true;
                        }
                    }
                }

                ErrorMessages::addFieldError(ErrorMessages::STARTS_WITH, $parentPath, $fieldName, $property, $value, $errors, self::valuesList($constraint->values));

                return false;

            case Ulid::class:
                if (is_string($value) && strlen($value) === 26) {
                    $valid = true;

                    for ($i = 0; $i < 26; $i++) {
                        $c = $value[$i];

                        if ($c === 'i' || $c === 'l' || $c === 'o' || $c === 'u' || $c === 'I' || $c === 'L' || $c === 'O' || $c === 'U') {
                            $valid = false;

                            break;
                        }

                        if (! ctype_alnum($c)) {
                            $valid = false;

                            break;
                        }
                    }

                    if ($valid) {
                        return true;
                    }
                }

                ErrorMessages::addFieldError(ErrorMessages::ULID, $parentPath, $fieldName, $property, $value, $errors);

                return false;

            case Uuid::class:
                if (is_string($value) && strlen($value) === 36) {
                    $valid = true;

                    for ($i = 0; $i < 36; $i++) {
                        $c = $value[$i];

                        if ($i === 8 || $i === 13 || $i === 18 || $i === 23) {
                            if ($c !== '-') {
                                $valid = false;

                                break;
                            }

                            continue;
                        }

                        if (! ctype_xdigit($c)) {
                            $valid = false;

                            break;
                        }
                    }

                    if ($valid) {
                        return true;
                    }
                }

                ErrorMessages::addFieldError(ErrorMessages::UUID, $parentPath, $fieldName, $property, $value, $errors);

                return false;
        }

        return true;
    }

    /**
     * Union declared directly on a non-array property: the whole value must
     * match at least one arm exactly.
     */
    private static function runUnionScalar(
        Union $constraint,
        mixed $value,
        ?string $parentPath,
        string $fieldName,
        ReflectionProperty $property,
        array &$errors,
    ): bool {
        if (is_array($value)) {
            throw new InvalidBaseModelException('Union cannot be applied directly to array values; use Sequence(of: new Union(...)) or Dict(of: ...) instead.');
        }

        foreach ($constraint->of as $arm) {
            if (self::unionArmMatchesScalar($arm, $value)) {
                return true;
            }
        }

        ErrorMessages::addFieldError(ErrorMessages::UNION, $parentPath, $fieldName, $property, $value, $errors, self::unionArmsExpected($constraint->of));

        return false;
    }

    /**
     * Whether a Union arm matches a scalar value exactly (no coercion).
     */
    private static function unionArmMatchesScalar(mixed $arm, mixed $value): bool
    {
        if (is_int($arm) || $arm instanceof Type) {
            $mask = is_int($arm) ? $arm : $arm->value;

            if (($mask & Masks::DICT) !== 0) {
                $mask = ($mask & ~Masks::DICT) | Masks::ARRAY;
            }

            return (Masks::of($value) & $mask) !== 0;
        }

        if (is_string($arm)) {
            return is_object($value) && $value instanceof $arm;
        }

        if ($arm instanceof Intersection) {
            if (! is_object($value)) {
                return false;
            }

            foreach ($arm->of as $class) {
                if (! $value instanceof $class) {
                    return false;
                }
            }

            return true;
        }

        // Sequence/Union/Dict shape arms never match a scalar
        return false;
    }

    /**
     * Renders the arm list of a Union constraint like a union type.
     *
     * @param  list<mixed>  $arms
     */
    private static function unionArmsExpected(array $arms): string
    {
        $parts = [];

        foreach ($arms as $arm) {
            if (is_string($arm)) {
                $parts[] = $arm;

                continue;
            }

            if ($arm instanceof Intersection) {
                $members = [];

                foreach ($arm->of as $class) {
                    if (is_string($class)) {
                        $members[] = $class;
                    }
                }

                $parts[] = $members === [] ? 'mixed' : implode(' and ', $members);

                continue;
            }

            if ($arm instanceof Type) {
                $arm = $arm->value;
            }

            if (is_int($arm)) {
                $mask = $arm;

                if (($mask & Masks::DICT) !== 0) {
                    $mask = ($mask & ~Masks::DICT) | Masks::ARRAY;
                }

                $names = [];

                foreach (Masks::BASIC_NAMES as [$bit, $name]) {
                    if (($mask & $bit) === $bit) {
                        $names[] = $name;
                    }
                }

                $parts[] = $names === [] ? 'mixed' : implode(', ', $names);

                continue;
            }

            // Sequence/Union/Dict nested-shape arm
            $parts[] = 'array';
        }

        $total = count($parts);

        if ($total === 0) {
            return 'mixed';
        }

        $result = '';

        foreach ($parts as $index => $part) {
            if ($index > 0) {
                $result .= $index + 1 === $total ? ' or ' : ', ';
            }

            $result .= $part;
        }

        return $result;
    }

    /**
     * The consistency rule between a Union attribute and a union or
     * nullable native hint: the arms must repeat the hint's type-hints
     * exactly (order-insensitive, class names case-insensitive).
     */
    private static function checkUnionMatchesHint(Union $union, ReflectionProperty $property, TypePlan $plan): void
    {
        $hintMask = $plan->basicMask;
        $unionMask = 0;
        $hasShapeArm = false;

        foreach ($union->of as $arm) {
            if (is_string($arm)) {
                continue;
            }

            if (is_int($arm) || $arm instanceof Type) {
                $mask = is_int($arm) ? $arm : $arm->value;

                if (($mask & Masks::DICT) !== 0) {
                    $mask = ($mask & ~Masks::DICT) | Masks::ARRAY;
                }

                $unionMask |= $mask;

                continue;
            }

            $hasShapeArm = true;
        }

        $matches = ! $hasShapeArm && $unionMask === $hintMask;

        if ($matches && $plan->isUnion && ! $plan->hasDnfArm) {
            $matches = self::unionClassArmsMatchHint($union->of, $plan);
        }

        if ($matches) {
            return;
        }

        $type = $property->getType();

        throw new InvalidBaseModelException(sprintf(
            'Union attribute on property %s::$%s must contain the same type-hints as the native union hint (%s); got (%s).',
            $property->getDeclaringClass()->getName(),
            $property->getName(),
            $type === null ? 'mixed' : ErrorMessages::expectedForHint($type),
            self::unionArmsExpected($union->of)
        ));
    }

    /**
     * The Union's class-name arms must equal the hint's named arms
     * (case-insensitive, same count).
     *
     * @param  list<mixed>  $arms
     */
    private static function unionClassArmsMatchHint(array $arms, TypePlan $plan): bool
    {
        $hintNames = [];

        foreach ($plan->classes as $arm) {
            $hintNames[] = strtolower($arm['class']);
        }

        $unionNames = [];

        foreach ($arms as $arm) {
            if (is_string($arm)) {
                $unionNames[] = strtolower(ltrim($arm, '\\'));
            }
        }

        if (count($unionNames) !== count($hintNames)) {
            return false;
        }

        foreach ($unionNames as $name) {
            if (! in_array($name, $hintNames, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Renders a value list for the In/Contains/StartsWith/EndsWith
     * messages: each value through Value::toString, joined with ", ".
     *
     * @param  list<mixed>  $values
     */
    private static function valuesList(array $values): string
    {
        $parts = [];

        foreach ($values as $value) {
            $parts[] = Value::toString($value);
        }

        return implode(', ', $parts);
    }

    /**
     * Renders the {expected} part of a Size error.
     */
    private static function sizeExpected(bool $hasMin, bool $hasMax, ?int $min, ?int $max): string
    {
        if ($hasMin && $hasMax) {
            return 'between '.$min.' and '.$max;
        }

        if ($hasMin) {
            return 'of at least '.$min;
        }

        return 'of at most '.$max;
    }
}
