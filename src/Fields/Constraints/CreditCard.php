<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;
use Attributes\Strictify\Internal\ErrorMessages;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class CreditCard implements Field
{
    public function validate(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $digits = '';

        foreach (str_split($value) as $c) {
            if ($c === ' ' || $c === '-') {
                continue;
            }

            if ($c < '0' || $c > '9') {
                return false;
            }

            $digits .= $c;
        }

        $n = strlen($digits);

        if ($n < 12 || $n > 19) {
            return false;
        }

        // Luhn checksum
        $sum = 0;
        $alt = false;

        for ($i = $n - 1; $i >= 0; $i--) {
            $d = (int) $digits[$i];

            if ($alt) {
                $d *= 2;

                if ($d > 9) {
                    $d -= 9;
                }
            }

            $sum += $d;
            $alt = ! $alt;
        }

        return $sum % 10 === 0;
    }

    /**
     * The error to report for a value that failed validate(): the catalog
     * error key and the {expected} placeholder override.
     *
     * @return array{string, string|null}
     */
    public function error(mixed $value): array
    {
        return [ErrorMessages::CREDIT_CARD, null];
    }
}
