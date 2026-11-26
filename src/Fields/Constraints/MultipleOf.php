<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;
use Attributes\Strictify\Internal\ErrorMessages;
use Attributes\Strictify\Internal\Value;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class MultipleOf implements Field
{
    private const EPSILON = 1e-9;

    public int|float $value;

    public function __construct(int|float $value)
    {
        $this->value = $value;
    }

    public function validate(mixed $value): bool
    {
        if (! is_int($value) && ! is_float($value)) {
            return false;
        }

        $bound = $this->value;

        if (is_int($value) && is_int($bound)) {
            return $bound === 0 ? $value === 0 : $value % $bound === 0;
        }

        $number = (float) $value;
        $divisor = (float) $bound;

        // A zero divisor has exactly one multiple, 0, for int and float
        // alike; fmod() would yield NAN for every pair and reject 0.0
        // itself
        if ($divisor == 0.0) {
            return $number == 0.0;
        }

        $remainder = fmod($number, $divisor);
        $tolerance = abs($divisor) * self::EPSILON;

        return abs($remainder) <= $tolerance || abs(abs($remainder) - abs($divisor)) <= $tolerance;
    }

    /**
     * The error to report for a value that failed validate(): the catalog
     * error key and the {expected} placeholder override.
     *
     * @return array{string, string|null}
     */
    public function error(mixed $value): array
    {
        return [ErrorMessages::MULTIPLE_OF, Value::toString($this->value)];
    }

    /**
     * The compile-time argument rule: every comparison against NAN is
     * false, so a NAN bound would silently turn the constraint into an
     * always-fail (or always-pass) rule.
     */
    public function argError(): ?string
    {
        if (is_float($this->value) && is_nan($this->value)) {
            return '$value must be a number, NAN given.';
        }

        return null;
    }
}
