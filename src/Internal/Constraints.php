<?php

declare(strict_types=1);

namespace Attributes\Strictify\Internal;

use Attributes\Strictify\Exceptions\InvalidBaseModelException;
use Attributes\Strictify\Fields\ConfigAwareConstraint;
use Attributes\Strictify\Fields\Constraint;
use Attributes\Strictify\Fields\Constraints\Alnum;
use Attributes\Strictify\Fields\Constraints\Alpha;
use Attributes\Strictify\Fields\Constraints\AlphaDash;
use Attributes\Strictify\Fields\Constraints\Ascii;
use Attributes\Strictify\Fields\Constraints\Base64;
use Attributes\Strictify\Fields\Constraints\Between;
use Attributes\Strictify\Fields\Constraints\Contains;
use Attributes\Strictify\Fields\Constraints\ContainsAny;
use Attributes\Strictify\Fields\Constraints\CreditCard;
use Attributes\Strictify\Fields\Constraints\DateTime;
use Attributes\Strictify\Fields\Constraints\Digit;
use Attributes\Strictify\Fields\Constraints\Domain;
use Attributes\Strictify\Fields\Constraints\Email;
use Attributes\Strictify\Fields\Constraints\EndsWith;
use Attributes\Strictify\Fields\Constraints\Even;
use Attributes\Strictify\Fields\Constraints\HexRgbColor;
use Attributes\Strictify\Fields\Constraints\Iban;
use Attributes\Strictify\Fields\Constraints\In;
use Attributes\Strictify\Fields\Constraints\Ip;
use Attributes\Strictify\Fields\Constraints\Isbn;
use Attributes\Strictify\Fields\Constraints\Json;
use Attributes\Strictify\Fields\Constraints\MacAddress;
use Attributes\Strictify\Fields\Constraints\Max;
use Attributes\Strictify\Fields\Constraints\Min;
use Attributes\Strictify\Fields\Constraints\MultipleOf;
use Attributes\Strictify\Fields\Constraints\Negative;
use Attributes\Strictify\Fields\Constraints\Odd;
use Attributes\Strictify\Fields\Constraints\Positive;
use Attributes\Strictify\Fields\Constraints\PrimeNumber;
use Attributes\Strictify\Fields\Constraints\Regex;
use Attributes\Strictify\Fields\Constraints\Size;
use Attributes\Strictify\Fields\Constraints\Slug;
use Attributes\Strictify\Fields\Constraints\StartsWith;
use Attributes\Strictify\Fields\Constraints\Ulid;
use Attributes\Strictify\Fields\Constraints\Url;
use Attributes\Strictify\Fields\Constraints\Uuid;
use Attributes\Strictify\Fields\Constraints\Version;
use Attributes\Strictify\Fields\Constraints\Xdigit;
use Attributes\Strictify\Fields\Dict;
use Attributes\Strictify\Fields\Intersection;
use Attributes\Strictify\Fields\Sequence;
use Attributes\Strictify\Fields\Union;
use Attributes\Strictify\Type;
use ReflectionAttribute;
use ReflectionProperty;

/**
 * Collects, compiles and runs the constraint attributes of a model
 * property. Each built-in constraint carries its own validate()/error()
 * logic in its class; userland constraints implement the Constraint
 * interface; a Union declared directly on a property validates the whole
 * value through Union::validateScalar().
 *
 * @internal
 */
final class Constraints
{
    /**
     * The built-in constraint classes, instantiated once per property.
     */
    private const BUILTIN = [
        AlphaDash::class,
        Ascii::class,
        Between::class,
        Contains::class,
        DateTime::class,
        EndsWith::class,
        In::class,
        Max::class,
        Min::class,
        MultipleOf::class,
        Regex::class,
        Size::class,
        StartsWith::class,
        Ulid::class,
        Uuid::class,
        Email::class,
        Url::class,
        Domain::class,
        Ip::class,
        Iban::class,
        Isbn::class,
        CreditCard::class,
        Version::class,
        MacAddress::class,
        Alnum::class,
        Alpha::class,
        Digit::class,
        Xdigit::class,
        Slug::class,
        Base64::class,
        Json::class,
        HexRgbColor::class,
        Even::class,
        Odd::class,
        Positive::class,
        Negative::class,
        PrimeNumber::class,
        ContainsAny::class,
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

            if (in_array($name, self::BUILTIN, true) || $name === Union::class) {
                $constraints[] = self::instantiateBuiltin($attribute, $property);

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
     * Instantiates a built-in constraint attribute once per field compile.
     * A rejected constructor argument belongs to the model declaration, so
     * the engine TypeError/ValueError is rewritten into
     * InvalidBaseModelException; afterwards the constraint's own argument
     * rules reject the arguments that would make it unsatisfiable or a
     * no-op (inverted or NAN bounds, empty value lists, a pattern that
     * does not compile, an empty domain list).
     */
    private static function instantiateBuiltin(ReflectionAttribute $attribute, ReflectionProperty $property): object
    {
        try {
            $instance = $attribute->newInstance();
        } catch (\TypeError|\ValueError $e) {
            throw new InvalidBaseModelException($e->getMessage(), 0, $e);
        }

        if (method_exists($instance, 'argError')) {
            $reason = $instance->argError();

            if ($reason !== null) {
                throw new InvalidBaseModelException(sprintf(
                    'Attribute "%s" on %s::$%s: %s',
                    $attribute->getName(),
                    $property->getDeclaringClass()->getName(),
                    $property->getName(),
                    $reason
                ));
            }
        }

        return $instance;
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

            if (($name === Sequence::class || $name === Dict::class || $name === Intersection::class) && ! $arrayLike) {
                throw new InvalidBaseModelException(sprintf(
                    'Sequence/Dict/Intersection can only be declared on array-like properties (array, a union containing array, mixed or untyped); property %s::$%s is not array-like.',
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
        // null is not a constrained value: a nullable property accepts it
        // (the type check already passed it), exactly like a missing key
        // backed by a null default. Running the constraints on null would
        // make ?T properties impossible to leave empty.
        if ($value === null) {
            return true;
        }

        if ($constraint instanceof Union) {
            if ($constraint->validateScalar($value)) {
                return true;
            }

            ErrorMessages::addFieldError(ErrorMessages::UNION, $parentPath, $fieldName, $property, $value, $errors, $constraint->armsExpected());

            return false;
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

        // Built-in constraint: validate() and the error descriptor live in
        // the constraint class itself
        if ($constraint->validate($value)) {
            return true;
        }

        [$key, $expected] = $constraint->error($value);

        ErrorMessages::addFieldError($key, $parentPath, $fieldName, $property, $value, $errors, $expected);

        return false;
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
            $union->armsExpected()
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
}
