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
}
