<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class Alias implements Field
{
    public function __construct(public string $name) {}
}
