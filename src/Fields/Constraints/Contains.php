<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class Contains implements Field
{
    public array $values;

    public function __construct(string|int|float ...$values)
    {
        if ($values === []) {
            throw new \ArgumentCountError('Attributes\Strictify\Fields\Constraints\Contains::__construct() expects at least 1 argument, 0 given');
        }

        $this->values = $values;
    }
}
