<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;
use Attributes\Strictify\Internal\ErrorMessages;
use Attributes\Strictify\Internal\Value;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class ContainsAny implements Field
{
    public array $values;

    public function __construct(string|int|float ...$values)
    {
        $this->values = $values;
    }

    public function validate(mixed $value): bool
    {
        if (is_string($value)) {
            // String value: at least one declared value must appear as a
            // substring; numeric declared values match by their string
            // representation
            foreach ($this->values as $needle) {
                if (str_contains($value, (string) $needle)) {
                    return true;
                }
            }

            return false;
        }

        if (is_array($value)) {
            // Array value: at least one declared value must be present
            // (strict comparison)
            foreach ($this->values as $needle) {
                foreach ($value as $element) {
                    if ($needle === $element) {
                        return true;
                    }
                }
            }

            return false;
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
        return [ErrorMessages::CONTAINS_ANY, Value::listString($this->values)];
    }
}
