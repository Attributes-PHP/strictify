<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class ErrorMessage implements Field
{
    public function __construct(
        public string $required = 'Field is required',
        public string $type = 'Must be {expected}',
    ) {}
}
