<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class StartsWith implements Field
{
    public array $values;

    public function __construct(string|int|float ...$values)
    {
        $this->values = $values;
    }
}
