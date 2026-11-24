<?php

declare(strict_types=1);

namespace Attributes\Strictify\Internal;

use Attributes\Strictify\BaseModel;
use ReflectionProperty;

/**
 * Serializes a BaseModel into an array: public properties only,
 * uninitialized properties skipped, #[Sensitive] properties omitted unless
 * requested, DateTimeInterface values formatted as strings (using the
 * property's #[DateTime] constraint format and timezone when declared),
 * nested models recursed with their own alias generator.
 *
 * @internal
 */
final class ToDict
{
    private const DEFAULT_PARSE_FORMAT = 'X-m-d\\TH:i:sP';

    private const DEFAULT_OUTPUT_FORMAT = 'Y-m-d\\TH:i:sP';

    /**
     * @var array<string, list<array{property: ReflectionProperty, name: string, fieldName: string, sensitive: bool}>>
     */
    private static array $entryCache = [];

    public static function serialize(BaseModel $model, bool $includeSensitive, bool $useAlias): array
    {
        $class = $model::class;
        $configs = ResolvedConfigs::resolve($class);
        $generator = $useAlias ? $configs->aliasGenerator : null;

        $result = [];

        foreach (self::entries($class, $generator) as $entry) {
            if ($entry['sensitive'] && ! $includeSensitive) {
                continue;
            }

            $property = $entry['property'];
            $name = $entry['name'];

            if (self::hasHooks($property)) {
                // Hooked (incl. virtual) properties are read through the
                // engine so the get hook runs
                if (! $property->isVirtual() && ! $property->isInitialized($model)) {
                    continue;
                }

                $value = $model->{$name};
            } else {
                if (! $property->isInitialized($model)) {
                    continue;
                }

                $value = $property->getValue($model);
            }

            $key = $useAlias ? $entry['fieldName'] : $name;
            $result[$key] = self::serializeValue($value, $includeSensitive, $useAlias, $property);
        }

        return $result;
    }

    /**
     * Serializes a single value: DateTimeInterface -> string, BaseModel ->
     * recursive dict, array -> recurse into elements, else copy.
     */
    private static function serializeValue(mixed $value, bool $includeSensitive, bool $useAlias, ?ReflectionProperty $property): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            $format = self::DEFAULT_OUTPUT_FORMAT;
            $timezone = null;

            if ($property !== null) {
                $dateTime = Constraints::dateTimeOf($property);

                if ($dateTime !== null) {
                    if ($dateTime->format !== self::DEFAULT_PARSE_FORMAT) {
                        $format = $dateTime->format;
                    }

                    $timezone = $dateTime->getTimezoneObj();
                }
            }

            if ($timezone !== null) {
                // Apply the constraint timezone, format, then restore so
                // the model's object is not mutated
                $original = $value->getTimezone();
                $value->setTimezone($timezone);
                $formatted = $value->format($format);
                $value->setTimezone($original);

                return $formatted;
            }

            return $value->format($format);
        }

        if ($value instanceof BaseModel) {
            return self::serialize($value, $includeSensitive, $useAlias);
        }

        if (is_array($value)) {
            $result = [];

            foreach ($value as $key => $element) {
                $result[$key] = self::serializeValue($element, $includeSensitive, $useAlias, null);
            }

            return $result;
        }

        return $value;
    }

    /**
     * The prepared property entries of a model class hierarchy, cached per
     * class and alias generator.
     *
     * @return list<array{property: ReflectionProperty, name: string, fieldName: string, sensitive: bool}>
     */
    private static function entries(string $class, ?string $generator): array
    {
        $cacheKey = $class."\0".(string) $generator;

        if (isset(self::$entryCache[$cacheKey])) {
            return self::$entryCache[$cacheKey];
        }

        $entries = [];

        foreach (Hydrator::entriesFor($cacheKey, $class, $generator) as $entry) {
            $entries[] = [
                'property' => $entry['property'],
                'name' => $entry['name'],
                'fieldName' => $entry['fieldName'],
                'sensitive' => $entry['sensitive'],
            ];
        }

        return self::$entryCache[$cacheKey] = $entries;
    }

    /**
     * Whether the property declares hooks (PHP 8.4+ syntax).
     */
    private static function hasHooks(ReflectionProperty $property): bool
    {
        return PHP_VERSION_ID >= 80400 && $property->getHooks() !== [];
    }
}
