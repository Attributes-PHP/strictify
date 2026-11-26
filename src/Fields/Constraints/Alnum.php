<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;
use Attributes\Strictify\Internal\ErrorMessages;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class Alnum implements Field
{
    public string $extra;

    public function __construct(string $extra = '')
    {
        $this->extra = $extra;
    }

    public function validate(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $c = $value[$i];
            $isAlnum = ($c >= '0' && $c <= '9') || ($c >= 'a' && $c <= 'z') || ($c >= 'A' && $c <= 'Z');

            if (! $isAlnum && ! str_contains($this->extra, $c)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The error to report for a value that failed validate(): the catalog
     * error key and the {expected} placeholder override.
     *
     * @return array{string, string|null}
     */
    public function error(mixed $value): array
    {
        return [ErrorMessages::ALNUM, null];
    }
}
