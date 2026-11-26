<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;
use Attributes\Strictify\Internal\ErrorMessages;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class Size implements Field
{
    public ?int $min;

    public ?int $max;

    public function __construct(?int $min = null, ?int $max = null)
    {
        $this->min = $min;
        $this->max = $max;
    }

    public function validate(mixed $value): bool
    {
        $hasMin = $this->min !== null;
        $hasMax = $this->max !== null;

        if (! $hasMin && ! $hasMax) {
            return true;
        }

        if (is_string($value)) {
            $size = strlen($value);
        } elseif (is_array($value)) {
            $size = count($value);
        } else {
            return false;
        }

        if ($hasMin && $size < $this->min) {
            return false;
        }

        return ! ($hasMax && $size > $this->max);
    }

    /**
     * The error to report for a value that failed validate(): the catalog
     * error key and the {expected} placeholder override.
     *
     * @return array{string, string|null}
     */
    public function error(mixed $value): array
    {
        return [ErrorMessages::SIZE, $this->sizeExpected()];
    }

    /**
     * The compile-time argument rules: negative sizes are meaningless and
     * inverted bounds make the constraint unsatisfiable.
     */
    public function argError(): ?string
    {
        $hasMin = $this->min !== null;
        $hasMax = $this->max !== null;

        if ($hasMin && $this->min < 0) {
            return sprintf('$min must not be negative, %d given.', $this->min);
        }

        if ($hasMax && $this->max < 0) {
            return sprintf('$max must not be negative, %d given.', $this->max);
        }

        if ($hasMin && $hasMax && $this->min > $this->max) {
            return sprintf('$min (%d) must be less than or equal to $max (%d).', $this->min, $this->max);
        }

        return null;
    }

    /**
     * Renders the {expected} part of the size error.
     */
    private function sizeExpected(): string
    {
        $hasMin = $this->min !== null;
        $hasMax = $this->max !== null;

        if ($hasMin && $hasMax) {
            return 'between '.$this->min.' and '.$this->max;
        }

        if ($hasMin) {
            return 'of at least '.$this->min;
        }

        return 'of at most '.$this->max;
    }
}
