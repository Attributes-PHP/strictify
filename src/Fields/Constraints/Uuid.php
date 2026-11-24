<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;
use Attributes\Strictify\Internal\ErrorMessages;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class Uuid implements Field
{
    public function validate(mixed $value): bool
    {
        if (! is_string($value) || strlen($value) !== 36) {
            return false;
        }

        for ($i = 0; $i < 36; $i++) {
            $c = $value[$i];

            if ($i === 8 || $i === 13 || $i === 18 || $i === 23) {
                if ($c !== '-') {
                    return false;
                }

                continue;
            }

            if (! ctype_xdigit($c)) {
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
        return [ErrorMessages::UUID, null];
    }
}
