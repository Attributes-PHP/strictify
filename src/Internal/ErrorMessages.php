<?php

declare(strict_types=1);

namespace Attributes\Strictify\Internal;

use Attributes\Strictify\ErrorTranslator;
use Attributes\Strictify\Fields\ErrorMessage;
use Attributes\Strictify\Fields\Sensitive;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

/**
 * Error message templates, placeholder expansion and expected-type strings.
 *
 * @internal
 */
final class ErrorMessages
{
    public const REQUIRED = 'required';

    public const TYPE = 'type';

    public const ALPHA_DASH = 'alpha_dash';

    public const ALPHA_DASH_TYPE = 'alpha_dash_type';

    public const ASCII = 'ascii';

    public const ASCII_TYPE = 'ascii_type';

    public const BETWEEN = 'between';

    public const BETWEEN_TYPE = 'between_type';

    public const CONTAINS = 'contains';

    public const DATETIME = 'datetime';

    public const ENDS_WITH = 'ends_with';

    public const IN = 'in';

    public const MAX = 'max';

    public const MIN = 'min';

    public const MULTIPLE_OF = 'multiple_of';

    public const REGEX = 'regex';

    public const SIZE = 'size';

    public const STARTS_WITH = 'starts_with';

    public const ULID = 'ulid';

    public const UUID = 'uuid';

    public const UNION = 'union';

    public const SEQUENCE_KEYS = 'sequence_keys';

    public const EXTRA = 'extra';

    /**
     * The error keys in ErrorMessage constructor argument position order.
     */
    private const ORDER = [
        self::REQUIRED,
        self::TYPE,
        self::ALPHA_DASH,
        self::ALPHA_DASH_TYPE,
        self::ASCII,
        self::ASCII_TYPE,
        self::BETWEEN,
        self::BETWEEN_TYPE,
        self::CONTAINS,
        self::DATETIME,
        self::ENDS_WITH,
        self::IN,
        self::MAX,
        self::MIN,
        self::MULTIPLE_OF,
        self::REGEX,
        self::SIZE,
        self::STARTS_WITH,
        self::ULID,
        self::UUID,
        self::UNION,
        self::SEQUENCE_KEYS,
    ];

    private const DEFAULTS = [
        self::REQUIRED => 'Field is required',
        self::TYPE => 'Must be {expected}',
        self::ALPHA_DASH => 'Must only contain letters, numbers, dashes and underscores',
        self::ALPHA_DASH_TYPE => 'Must be a string containing only letters, numbers, dashes and underscores',
        self::ASCII => 'Must only contain ASCII characters',
        self::ASCII_TYPE => 'Must be a string containing only ASCII characters',
        self::BETWEEN => 'Must be between {expected}',
        self::BETWEEN_TYPE => 'Must be a number between {expected}',
        self::CONTAINS => 'Must contain {expected}',
        self::DATETIME => 'Must be a datetime in the format {expected}',
        self::ENDS_WITH => 'Must end with one of: {expected}',
        self::IN => 'Must be one of: {expected}',
        self::MAX => 'Must be at most {expected}',
        self::MIN => 'Must be at least {expected}',
        self::MULTIPLE_OF => 'Must be a multiple of {expected}',
        self::REGEX => 'Must match the pattern {expected}',
        self::SIZE => 'Must have a size {expected}',
        self::STARTS_WITH => 'Must start with one of: {expected}',
        self::ULID => 'Must be a valid ULID',
        self::UUID => 'Must be a valid UUID',
        self::UNION => 'Must be {expected}',
        self::SEQUENCE_KEYS => 'Must be a sequential list (keys 0, 1, 2, ...)',
        self::EXTRA => 'Extra inputs are not permitted',
    ];

    public const SENSITIVE_MASK = '***';

    /**
     * The resolved message templates per property and error type,
     * evaluated lazily so argument errors keep surfacing per type.
     *
     * @var array<string, string>
     */
    private static array $templateCache = [];

    /**
     * The expected-type string of each property hint.
     *
     * @var array<string, string>
     */
    private static array $expectedCache = [];

    /**
     * Drops the resolved template and expected-string caches; called when a
     * new translator is registered.
     */
    public static function clearCache(): void
    {
        self::$templateCache = [];
        self::$expectedCache = [];
    }

    /**
     * Adds an error for a field to the error collection.
     */
    public static function addFieldError(
        string $type,
        ?string $parentPath,
        string $fieldName,
        ?ReflectionProperty $property,
        mixed $value,
        array &$errors,
        ?string $expectedOverride = null,
    ): void {
        $template = self::template($type, $property);
        $message = self::replacePlaceholders($template, $fieldName, $value, $property, $expectedOverride);
        $path = ($parentPath === null || $parentPath === '') ? $fieldName : $parentPath.'.'.$fieldName;

        $errors[$path][] = $message;
    }

    /**
     * Adds an "extra inputs" error for an unknown input key.
     */
    public static function addExtraError(?string $parentPath, string $key, mixed $value, array &$errors): void
    {
        $template = self::translate(self::EXTRA, self::DEFAULTS[self::EXTRA]);
        $message = self::replacePlaceholders($template, $key, $value, null, '');
        $path = ($parentPath === null || $parentPath === '') ? $key : $parentPath.'.'.$key;

        $errors[$path][] = $message;
    }

    /**
     * Adds a userland constraint message (never translated) to the error
     * collection.
     */
    public static function addCustomMessage(
        string $template,
        ?string $parentPath,
        string $fieldName,
        ?ReflectionProperty $property,
        mixed $value,
        array &$errors,
    ): void {
        $message = self::replacePlaceholders($template, $fieldName, $value, $property, null);
        $path = ($parentPath === null || $parentPath === '') ? $fieldName : $parentPath.'.'.$fieldName;

        $errors[$path][] = $message;
    }

    /**
     * Whether the property carries the #[Sensitive] attribute: its {value}
     * placeholder renders the mask instead of the raw value.
     */
    public static function isSensitive(?ReflectionProperty $property): bool
    {
        return $property !== null && $property->getAttributes(Sensitive::class) !== [];
    }

    /**
     * Renders the expected type string of a property type hint, mirroring
     * the engine's rendering of unions, classes and enum cases.
     */
    public static function expectedForHint(ReflectionType $type): string
    {
        if ($type instanceof \ReflectionIntersectionType) {
            return 'mixed';
        }

        $arms = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];

        $basicMask = 0;
        $named = [];

        foreach ($arms as $arm) {
            if (! $arm instanceof ReflectionNamedType) {
                continue;
            }

            if ($arm->allowsNull()) {
                $basicMask |= Masks::NULL;
            }

            if ($arm->isBuiltin()) {
                $basicMask |= Masks::forName($arm->getName());

                continue;
            }

            $named[] = $arm->getName();
        }

        $basicNames = [];
        $basicTotal = 0;

        foreach (Masks::BASIC_NAMES as [$mask, $name]) {
            if ($basicMask & $mask) {
                $basicNames[] = $name;
                $basicTotal++;
            }
        }

        $hasEnum = false;

        foreach ($named as $name) {
            if (enum_exists($name)) {
                $hasEnum = true;
            }
        }

        // Basic types only
        if ($named === []) {
            if ($basicTotal <= 1) {
                foreach ($arms as $arm) {
                    return self::typeName($arm, $basicMask);
                }
            }

            return self::join($basicNames);
        }

        // Class types only: class names or-joined
        if ($basicTotal === 0 && ! $hasEnum) {
            return implode(' or ', $named);
        }

        // Mixed basic, class and enum types: basic names first, then named
        // types in declaration order; enums expand to one part per case
        $parts = $basicNames;

        foreach ($named as $name) {
            if (enum_exists($name)) {
                foreach ((new \ReflectionEnum($name))->getCases() as $case) {
                    $enumValue = $case->getValue();
                    $parts[] = Value::toString($enumValue instanceof \BackedEnum ? $enumValue->value : $enumValue->name);
                }

                continue;
            }

            $parts[] = $name;
        }

        return self::join($parts);
    }

    private static function typeName(ReflectionNamedType $arm, int $basicMask): string
    {
        if ($arm->isBuiltin()) {
            $mask = Masks::forName($arm->getName());

            if ($mask !== 0 && ($basicMask & $mask) === $mask) {
                foreach (Masks::BASIC_NAMES as [$basicMaskEntry, $name]) {
                    if ($basicMaskEntry === $mask) {
                        return $name;
                    }
                }
            }

            return 'mixed';
        }

        return $arm->getName();
    }

    /**
     * Joins type names with ", " and a final " or ".
     */
    private static function join(array $parts): string
    {
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
     * Returns the error message template for an error type: the custom
     * template declared with #[ErrorMessage] when the property carries one,
     * the default template otherwise. Both go through the registered
     * translator, when any.
     */
    private static function template(string $type, ?ReflectionProperty $property): string
    {
        if ($property === null) {
            return self::translate($type, self::DEFAULTS[$type]);
        }

        $cacheKey = $property->getDeclaringClass()->getName().'::$'.$property->getName()."\0".$type;

        if (isset(self::$templateCache[$cacheKey])) {
            return self::$templateCache[$cacheKey];
        }

        return self::$templateCache[$cacheKey] = self::resolveTemplate($type, $property);
    }

    private static function resolveTemplate(string $type, ReflectionProperty $property): string
    {
        if ($type === self::EXTRA) {
            return self::translate($type, self::DEFAULTS[$type]);
        }

        $attributes = $property->getAttributes(ErrorMessage::class);

        if ($attributes === []) {
            return self::translate($type, self::DEFAULTS[$type]);
        }

        $argumentName = $type;
        $argumentPosition = array_search($type, self::ORDER, true);
        $position = 0;

        foreach ($attributes[0]->getArguments() as $name => $value) {
            if (is_int($name)) {
                if ($position !== $argumentPosition) {
                    $position++;

                    continue;
                }
            } elseif (strcasecmp((string) $name, $argumentName) !== 0) {
                continue;
            }

            if (is_string($value)) {
                // A custom #[ErrorMessage] value has no stable key: the
                // translator receives null and the attribute string
                return self::translate(null, $value);
            }

            if ($value !== null) {
                throw new \ValueError('Only string arguments are valid for Attributes\Strictify\Fields\ErrorMessage::construct(...)');
            }

            break;
        }

        return self::translate($type, self::DEFAULTS[$type]);
    }

    /**
     * Runs the registered translator, when any. A custom message is passed
     * with a null key; built-in keys carry their stable name. A non-string
     * non-null return is an error.
     */
    private static function translate(?string $key, string $message): string
    {
        $translator = ErrorTranslator::get();

        if ($translator === null) {
            return $message;
        }

        $result = $translator($key, $message);

        if ($result !== null && ! is_string($result)) {
            throw new \ValueError('The default error messages translator must return ?string');
        }

        return $result ?? $message;
    }

    /**
     * Substitutes the {field}, {value} and {expected} placeholders of an
     * error message template in a single pass. {value} renders the mask for
     * #[Sensitive] properties.
     */
    private static function replacePlaceholders(
        string $template,
        string $fieldName,
        mixed $value,
        ?ReflectionProperty $property,
        ?string $expectedOverride,
    ): string {
        $expected = $expectedOverride;

        if ($expected === null && str_contains($template, '{expected}')) {
            $cacheKey = $property->getDeclaringClass()->getName().'::$'.$property->getName();
            $type = $property->getType();

            $expected = self::$expectedCache[$cacheKey] ??= $type === null ? 'mixed' : self::expectedForHint($type);
        }

        $valueString = self::isSensitive($property) ? self::SENSITIVE_MASK : Value::toString($value);

        $replacements = [
            '{field}' => $fieldName,
            '{value}' => $valueString,
            '{expected}' => $expected ?? '',
        ];

        $result = '';
        $length = strlen($template);
        $offset = 0;

        while ($offset < $length) {
            $matched = false;

            foreach ($replacements as $search => $replace) {
                $searchLength = strlen($search);

                if (substr_compare($template, $search, $offset, $searchLength) === 0) {
                    $result .= $replace;
                    $offset += $searchLength;
                    $matched = true;

                    break;
                }
            }

            if (! $matched) {
                $result .= $template[$offset];
                $offset++;
            }
        }

        return $result;
    }
}
