<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class Min implements Field
{
    public int|float $value;

    public function __construct(int|float $value)
    {
        $this->value = $value;
    }
}
