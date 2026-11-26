<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;
use Attributes\Strictify\Internal\ErrorMessages;
use Attributes\Strictify\Internal\Value;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class StartsWith implements Field
{
    public array $values;

    public function __construct(string|int|float ...$values)
    {
        $this->values = $values;
    }

    public function validate(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        foreach ($this->values as $prefix) {
            $prefixString = (string) $prefix;

            if (strlen($prefixString) <= strlen($value) && substr_compare($value, $prefixString, 0, strlen($prefixString)) === 0) {
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
        return [ErrorMessages::STARTS_WITH, Value::listString($this->values)];
    }

    /**
     * The compile-time argument rules: an empty list fails every input,
     * and an empty prefix matches every value.
     */
    public function argError(): ?string
    {
        if ($this->values === []) {
            return 'at least one prefix must be declared.';
        }

        foreach ($this->values as $prefix) {
            if ($prefix === '') {
                return 'an empty string matches every value: declare only non-empty values.';
            }
        }

        return null;
    }
}
