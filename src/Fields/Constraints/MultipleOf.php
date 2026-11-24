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

        if ($divisor == 0.0 && $number != 0.0) {
            return false;
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
}
