<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;
use Attributes\Strictify\Internal\ErrorMessages;
use Attributes\Strictify\Internal\Value;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class Between implements Field
{
    public int|float $min;

    public int|float $max;

    public function __construct(int|float $min, int|float $max)
    {
        $this->min = $min;
        $this->max = $max;
    }

    public function validate(mixed $value): bool
    {
        return (is_int($value) || is_float($value)) && $value >= $this->min && $value <= $this->max;
    }

    /**
     * The error to report for a value that failed validate(): the catalog
     * error key and the {expected} placeholder override.
     *
     * @return array{string, string|null}
     */
    public function error(mixed $value): array
    {
        $expected = Value::toString($this->min).' and '.Value::toString($this->max);

        return [(is_int($value) || is_float($value)) ? ErrorMessages::BETWEEN : ErrorMessages::BETWEEN_TYPE, $expected];
    }

    /**
     * The compile-time argument rules: a NAN bound compares false against
     * everything, and inverted bounds make the constraint unsatisfiable.
     */
    public function argError(): ?string
    {
        foreach (['min' => $this->min, 'max' => $this->max] as $argument => $bound) {
            if (is_float($bound) && is_nan($bound)) {
                return sprintf('$%s must be a number, NAN given.', $argument);
            }
        }

        if ((float) $this->min > (float) $this->max) {
            return sprintf(
                '$min (%s) must be less than or equal to $max (%s).',
                Value::toString($this->min),
                Value::toString($this->max)
            );
        }

        return null;
    }
}
