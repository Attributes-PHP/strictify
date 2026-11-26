<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;
use Attributes\Strictify\Internal\ErrorMessages;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class Ulid implements Field
{
    private const EXCLUDED = ['i', 'l', 'o', 'u', 'I', 'L', 'O', 'U'];

    public function validate(mixed $value): bool
    {
        if (! is_string($value) || strlen($value) !== 26) {
            return false;
        }

        for ($i = 0; $i < 26; $i++) {
            $c = $value[$i];

            if (in_array($c, self::EXCLUDED, true) || ! ctype_alnum($c)) {
                return false;
            }

            // A ULID encodes 128 bits in 26 base32 characters: the first
            // character can only be 0-7, otherwise the value overflows
            if ($i === 0 && $c > '7') {
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
        return [ErrorMessages::ULID, null];
    }
}
