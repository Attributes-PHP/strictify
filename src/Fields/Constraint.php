<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields;

interface Constraint extends Field
{
    public function validate(mixed $value): bool;

    public function message(): string;
}
