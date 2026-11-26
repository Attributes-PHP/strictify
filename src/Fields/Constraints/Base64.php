<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;
use Attributes\Strictify\Internal\ErrorMessages;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class Base64 implements Field
{
    public function validate(mixed $value): bool
    {
        return is_string($value)
            && strlen($value) % 4 === 0
            && preg_match('~^[A-Za-z0-9+/]*={0,2}$~', $value) === 1;
    }

    /**
     * The error to report for a value that failed validate(): the catalog
     * error key and the {expected} placeholder override.
     *
     * @return array{string, string|null}
     */
    public function error(mixed $value): array
    {
        return [ErrorMessages::BASE64, null];
    }
}
