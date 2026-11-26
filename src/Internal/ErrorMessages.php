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
    /*
     * Stable error keys, one per error type: the same camelCase string is
     * the #[ErrorMessage] named argument and the key handed to the
     * translator registered via ErrorTranslator::set(). The `extra` key
     * has no owning property, so it is not an #[ErrorMessage] argument.
     */
    public const REQUIRED = 'required';

    public const TYPE = 'type';

    public const ALPHA_DASH = 'alphaDash';

    public const ALPHA_DASH_TYPE = 'alphaDashType';

    public const ASCII = 'ascii';

    public const ASCII_TYPE = 'asciiType';

    public const BETWEEN = 'between';

    public const BETWEEN_TYPE = 'betweenType';

    public const CONTAINS = 'contains';

    public const DATETIME = 'datetime';

    public const ENDS_WITH = 'endsWith';

    public const IN = 'in';

    public const MAX = 'max';

    public const MIN = 'min';

    public const MULTIPLE_OF = 'multipleOf';

    public const REGEX = 'regex';

    public const SIZE = 'size';

    public const STARTS_WITH = 'startsWith';

    public const ULID = 'ulid';

    public const UUID = 'uuid';

    public const EMAIL = 'email';

    public const EMAIL_DOMAIN = 'emailDomain';

    public const URL = 'url';

    public const URL_PROTOCOL = 'urlProtocol';

    public const URL_DOMAIN = 'urlDomain';

    public const DOMAIN = 'domain';

    public const IP = 'ip';

    public const IBAN = 'iban';

    public const ISBN = 'isbn';

    public const CREDIT_CARD = 'creditCard';

    public const VERSION = 'version';

    public const MAC_ADDRESS = 'macAddress';

    public const ALNUM = 'alnum';

    public const ALPHA = 'alpha';

    public const DIGIT = 'digit';

    public const XDIGIT = 'xdigit';

    public const SLUG = 'slug';

    public const BASE64 = 'base64';

    public const JSON = 'json';

    public const HEX_RGB_COLOR = 'hexRgbColor';

    public const EVEN = 'even';

    public const ODD = 'odd';

    public const POSITIVE = 'positive';

    public const NEGATIVE = 'negative';

    public const PRIME_NUMBER = 'primeNumber';

    public const CONTAINS_ANY = 'containsAny';

    public const UNION = 'union';

    public const SEQUENCE_KEYS = 'sequenceKeys';

    public const UNIQUE_VALUES = 'uniqueValues';

    public const EXTRA = 'extra';

    /**
     * The error keys in ErrorMessage constructor argument position order
     * (`extra` is discarded: extra inputs have no owning property).
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
        self::EMAIL,
        self::EMAIL_DOMAIN,
        self::URL,
        self::URL_PROTOCOL,
        self::URL_DOMAIN,
        self::DOMAIN,
        self::IP,
        self::IBAN,
        self::ISBN,
        self::CREDIT_CARD,
        self::VERSION,
        self::MAC_ADDRESS,
        self::ALNUM,
        self::ALPHA,
        self::DIGIT,
        self::XDIGIT,
        self::SLUG,
        self::BASE64,
        self::JSON,
        self::HEX_RGB_COLOR,
        self::EVEN,
        self::ODD,
        self::POSITIVE,
        self::NEGATIVE,
        self::PRIME_NUMBER,
        self::CONTAINS_ANY,
        self::UNION,
        self::SEQUENCE_KEYS,
        self::UNIQUE_VALUES,
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
        self::EMAIL => 'Must be a valid email address',
        self::EMAIL_DOMAIN => 'Must be an email address on an allowed domain: {expected}',
        self::URL => 'Must be a valid URL',
        self::URL_PROTOCOL => 'Must be a URL using an allowed protocol: {expected}',
        self::URL_DOMAIN => 'Must be a URL on an allowed domain: {expected}',
        self::DOMAIN => 'Must be a valid domain name',
        self::IP => 'Must be a valid IP address',
        self::IBAN => 'Must be a valid IBAN',
        self::ISBN => 'Must be a valid ISBN',
        self::CREDIT_CARD => 'Must be a valid credit card number',
        self::VERSION => 'Must be a valid version string',
        self::MAC_ADDRESS => 'Must be a valid MAC address',
        self::ALNUM => 'Must contain only letters and numbers',
        self::ALPHA => 'Must contain only letters',
        self::DIGIT => 'Must contain only digits',
        self::XDIGIT => 'Must contain only hexadecimal digits',
        self::SLUG => 'Must be a valid slug',
        self::BASE64 => 'Must be valid base64',
        self::JSON => 'Must be valid JSON',
        self::HEX_RGB_COLOR => 'Must be a valid hex RGB color',
        self::EVEN => 'Must be an even number',
        self::ODD => 'Must be an odd number',
        self::POSITIVE => 'Must be a positive number',
        self::NEGATIVE => 'Must be a negative number',
        self::PRIME_NUMBER => 'Must be a prime number',
        self::CONTAINS_ANY => 'Must contain at least one of: {expected}',
        self::UNION => 'Must be {expected}',
        self::SEQUENCE_KEYS => 'Must be a sequential list (keys 0, 1, 2, ...)',
        self::UNIQUE_VALUES => 'Must contain only unique values',
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
                throw new \TypeError(sprintf(
                    'Attributes\\Strictify\\Fields\\ErrorMessage::__construct(): Argument #%d ($%s) must be of type string, %s given',
                    $argumentPosition + 1,
                    $argumentName,
                    Value::typeName($value)
                ));
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
            throw new \TypeError('The default error messages translator must return ?string');
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
