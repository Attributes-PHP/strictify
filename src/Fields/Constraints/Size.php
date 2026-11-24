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
