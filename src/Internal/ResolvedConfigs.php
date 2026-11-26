<?php

declare(strict_types=1);

namespace Attributes\Strictify\Internal;

use Attributes\Strictify\Exceptions\InvalidBaseModelException;
use Attributes\Strictify\ModelConfigs;

/**
 * The ModelConfigs of a model class, resolved from its class attribute.
 *
 * A null (or omitted) extra is resolved per model class here: 'allow' when
 * the class is marked #[AllowDynamicProperties] (the flag is inherited by
 * child classes), 'ignore' otherwise.
 *
 * @internal
 */
final class ResolvedConfigs
{
    private static array $cache = [];

    private const ARGUMENT_NAMES = [
        'aliasGenerator',
        'strToLower',
        'strToUpper',
        'stripWhitespace',
        'extra',
        'strict',
        'stopAtFirstError',
    ];

    private function __construct(
        public readonly ?string $aliasGenerator,
        public readonly bool $strToLower,
        public readonly bool $strToUpper,
        public readonly bool $stripWhitespace,
        public readonly string $extra,
        public readonly bool $strict,
        public readonly bool $stopAtFirstError,
    ) {}

    /**
     * Resolves the ModelConfigs of a model class, walking up the
     * inheritance chain for the attribute, parsed once and cached per
     * class.
     */
    public static function resolve(string $class): self
    {
        if (isset(self::$cache[$class])) {
            return self::$cache[$class];
        }

        $generator = null;
        $strToLower = false;
        $strToUpper = false;
        $stripWhitespace = false;
        $extra = null;
        $strict = true;
        $stopAtFirstError = false;

        $attribute = null;

        for ($current = $class; $current !== false; $current = get_parent_class($current)) {
            $attributes = (new \ReflectionClass($current))->getAttributes(ModelConfigs::class);

            if ($attributes !== []) {
                $attribute = $attributes[0];

                break;
            }
        }

        if ($attribute !== null) {
            foreach ($attribute->getArguments() as $name => $value) {
                $index = is_int($name) ? $name : self::argumentIndex($name);
                $argumentNumber = $index + 1;
                $argumentName = self::ARGUMENT_NAMES[$index];

                switch ($index) {
                    case 0: // aliasGenerator
                        if ($value === null) {
                            break;
                        }

                        if (! is_string($value)) {
                            throw new InvalidBaseModelException(sprintf(
                                'Attributes\\Strictify\\ModelConfigs::__construct(): Argument #%d ($%s) must be of type ?string, %s given',
                                $argumentNumber,
                                $argumentName,
                                Value::typeName($value)
                            ));
                        }

                        if (! in_array(strtolower($value), ['pascal', 'camel', 'snake', 'kebab'], true)) {
                            throw new InvalidBaseModelException('Attributes\\Strictify\\ModelConfigs::__construct(): Argument #1 must be of one of the following options: pascal, camel, snake, kebab');
                        }

                        $generator = strtolower($value);

                        break;
                    case 4: // extra
                        if ($value === null) {
                            break;
                        }

                        if (! is_string($value)) {
                            throw new InvalidBaseModelException(sprintf(
                                'Attributes\\Strictify\\ModelConfigs::__construct(): Argument #%d ($%s) must be of type ?string, %s given',
                                $argumentNumber,
                                $argumentName,
                                Value::typeName($value)
                            ));
                        }

                        if (! in_array(strtolower($value), ['ignore', 'forbid', 'allow'], true)) {
                            throw new InvalidBaseModelException('Attributes\\Strictify\\ModelConfigs::__construct(): Argument #5 must be of one of the following options: ignore, forbid, allow');
                        }

                        $extra = strtolower($value);

                        break;
                    default: // strToLower, strToUpper, stripWhitespace, strict, stopAtFirstError
                        if (! is_bool($value)) {
                            throw new InvalidBaseModelException(sprintf(
                                'Attributes\\Strictify\\ModelConfigs::__construct(): Argument #%d ($%s) must be of type bool, %s given',
                                $argumentNumber,
                                $argumentName,
                                Value::typeName($value)
                            ));
                        }

                        switch ($index) {
                            case 1:
                                $strToLower = $value;

                                break;
                            case 2:
                                $strToUpper = $value;

                                break;
                            case 3:
                                $stripWhitespace = $value;

                                break;
                            case 5:
                                $strict = $value;

                                break;
                            case 6:
                                $stopAtFirstError = $value;

                                break;
                        }
                }
            }
        }

        $extra ??= self::allowsDynamicProperties($class) ? 'allow' : 'ignore';

        return self::$cache[$class] = new self(
            $generator,
            $strToLower,
            $strToUpper,
            $stripWhitespace,
            $extra,
            $strict,
            $stopAtFirstError,
        );
    }

    /**
     * Materializes the configs as a ModelConfigs instance (hook argument),
     * with the per-class resolved extra.
     */
    public function toModelConfigs(): ModelConfigs
    {
        return new ModelConfigs(
            $this->aliasGenerator,
            $this->strToLower,
            $this->strToUpper,
            $this->stripWhitespace,
            $this->extra,
            $this->strict,
            $this->stopAtFirstError,
        );
    }

    /**
     * Whether the class hierarchy carries #[AllowDynamicProperties].
     */
    private static function allowsDynamicProperties(string $class): bool
    {
        for ($current = $class; $current !== false; $current = get_parent_class($current)) {
            if ((new \ReflectionClass($current))->getAttributes(\AllowDynamicProperties::class) !== []) {
                return true;
            }
        }

        return false;
    }

    private static function argumentIndex(string $name): int
    {
        return match ($name) {
            'aliasGenerator' => 0,
            'strToLower' => 1,
            'strToUpper' => 2,
            'stripWhitespace' => 3,
            'extra' => 4,
            'strict' => 5,
            'stopAtFirstError' => 6,
            default => throw new \Error("Unknown named parameter \${$name}"),
        };
    }
}
