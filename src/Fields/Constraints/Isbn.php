<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;
use Attributes\Strictify\Internal\ErrorMessages;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class Isbn implements Field
{
    public function validate(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        // Strip dashes and spaces
        $digits = str_replace(['-', ' '], '', $value);
        $n = strlen($digits);

        if ($n === 10) {
            $sum = 0;

            for ($i = 0; $i < 10; $i++) {
                $c = $digits[$i];

                if ($c >= '0' && $c <= '9') {
                    $d = (int) $c;
                } elseif (($c === 'X' || $c === 'x') && $i === 9) {
                    $d = 10;
                } else {
                    return false;
                }

                $sum += ($i + 1) * $d;
            }

            return $sum % 11 === 0;
        }

        if ($n === 13) {
            $sum = 0;

            for ($i = 0; $i < 13; $i++) {
                $c = $digits[$i];

                if ($c < '0' || $c > '9') {
                    return false;
                }

                $sum += (int) $c * ($i % 2 === 0 ? 1 : 3);
            }

            return $sum % 10 === 0;
        }

        return false;
    }

    /**
     * The error to report for a value that failed validate(): the catalog
     * error key and the {expected} placeholder override.
     *
     * @return array{string, string|null}
     */
    public function error(mixed $value): array
    {
        return [ErrorMessages::ISBN, null];
    }
}
