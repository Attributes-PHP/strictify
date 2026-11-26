<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;
use Attributes\Strictify\Internal\ErrorMessages;
use Attributes\Strictify\Internal\Value;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class Min implements Field
{
    public int|float $value;

    public function __construct(int|float $value)
    {
        $this->value = $value;
    }

    public function validate(mixed $value): bool
    {
        return (is_int($value) || is_float($value)) && $value >= $this->value;
    }

    /**
     * The error to report for a value that failed validate(): the catalog
     * error key and the {expected} placeholder override.
     *
     * @return array{string, string|null}
     */
    public function error(mixed $value): array
    {
        return [ErrorMessages::MIN, Value::toString($this->value)];
    }

    /**
     * The compile-time argument rule: a NAN bound would make the
     * constraint silently pass every value.
     */
    public function argError(): ?string
    {
        if (is_float($this->value) && is_nan($this->value)) {
            return '$value must be a number, NAN given.';
        }

        return null;
    }
}
