<?php

declare(strict_types=1);

namespace Attributes\Strictify;

use Attributes\Strictify\Fields\Sensitive;
use Attributes\Strictify\Internal\ErrorMessages;
use Attributes\Strictify\Internal\ToDict;

abstract class BaseModel
{
    /**
     * Masks #[Sensitive] properties in debug output (print_r, var_dump,
     * ...): the dump shows the mask, the property keeps the real value.
     */
    public function __debugInfo(): array
    {
        $vars = get_object_vars($this);

        foreach ((new \ReflectionClass($this))->getProperties() as $property) {
            if ($property->isStatic() || ! array_key_exists($property->getName(), $vars)) {
                continue;
            }

            if ($property->getAttributes(Sensitive::class) !== []) {
                $vars[$property->getName()] = ErrorMessages::SENSITIVE_MASK;
            }
        }

        return $vars;
    }

    protected function beforeValidation(array $rawData, ModelConfigs $configs): array
    {
        return $rawData;
    }

    protected function afterValidation(array $rawData, ModelConfigs $configs): void {}

    /**
     * Serializes the model's public properties into an array. Keys are the
     * resolved field names (Alias / aliasGenerator) when $useAlias is set,
     * the raw property names otherwise. #[Sensitive] properties are omitted
     * unless $includeSensitive is set.
     */
    public function toDict(bool $includeSensitive = false, bool $useAlias = false): array
    {
        return ToDict::serialize($this, $includeSensitive, $useAlias);
    }
}
