<?php

declare(strict_types=1);

namespace Attributes\Strictify;

use Attributes\Strictify\Internal\ToDict;

abstract class BaseModel
{
    protected array $errors = [];

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
