<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;

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
}
