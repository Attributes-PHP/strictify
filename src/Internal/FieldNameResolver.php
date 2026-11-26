<?php

declare(strict_types=1);

namespace Attributes\Strictify\Internal;

use Attributes\Strictify\Exceptions\InvalidBaseModelException;
use Attributes\Strictify\Fields\Alias;
use ReflectionProperty;

/**
 * Resolves the raw-data field name of a property: the Alias attribute wins,
 * then the ModelConfigs alias generator, then the property name.
 *
 * @internal
 */
final class FieldNameResolver
{
    public static function resolve(ReflectionProperty $property, ?string $generator): string
    {
        $attributes = $property->getAttributes(Alias::class);

        if ($attributes !== []) {
            $arguments = $attributes[0]->getArguments();
            $name = $arguments[0] ?? $arguments['name'] ?? null;

            // The alias belongs to the model declaration: a non-string or
            // empty name is a malformed model, not a bad call
            if (! is_string($name)) {
                throw new InvalidBaseModelException(sprintf(
                    'The #[Alias] name of %s::$%s must be a string, %s given.',
                    $property->getDeclaringClass()->getName(),
                    $property->getName(),
                    Value::typeName($name)
                ));
            }

            if ($name === '') {
                throw new InvalidBaseModelException(sprintf(
                    'The #[Alias] name of %s::$%s must not be empty.',
                    $property->getDeclaringClass()->getName(),
                    $property->getName()
                ));
            }

            return $name;
        }

        return match ($generator) {
            'pascal' => CaseConvert::pascal($property->getName()),
            'camel' => CaseConvert::camel($property->getName()),
            'snake' => CaseConvert::snake($property->getName()),
            'kebab' => CaseConvert::kebab($property->getName()),
            default => $property->getName(),
        };
    }
}
