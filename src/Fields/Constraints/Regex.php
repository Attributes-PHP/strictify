<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class Regex implements Field
{
    public string $regex;

    public function __construct(string $regex)
    {
        $this->regex = $regex;
    }
}
