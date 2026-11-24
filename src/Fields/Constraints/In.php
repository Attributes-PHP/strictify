<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;
use Attributes\Strictify\Internal\ErrorMessages;
use Attributes\Strictify\Internal\Value;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class In implements Field
{
    public array $values;

    public function __construct(string|int|float|bool ...$values)
    {
        $this->values = $values;
    }

    public function validate(mixed $value): bool
    {
        foreach ($this->values as $allowed) {
            if ($allowed === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * The error to report for a value that failed validate(): the catalog
     * error key and the {expected} placeholder override.
     *
     * @return array{string, string|null}
     */
    public function error(mixed $value): array
    {
        return [ErrorMessages::IN, Value::listString($this->values)];
    }
}
