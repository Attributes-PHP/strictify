<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;
use Attributes\Strictify\Internal\ErrorMessages;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class Iban implements Field
{
    public function validate(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        // IBANs are case-insensitive in practice (printed and pasted in
        // either case): the checksum is computed on the uppercased form
        $normalized = strtoupper($value);

        if (strlen($normalized) >= 64) {
            return false;
        }

        if (preg_match('~^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$~', $normalized) !== 1) {
            return false;
        }

        // mod-97 checksum over the rearranged string (first 4 chars moved
        // to the end)
        $rearranged = substr($normalized, 4).substr($normalized, 0, 4);
        $remainder = 0;
        $length = strlen($rearranged);

        for ($i = 0; $i < $length; $i++) {
            $c = $rearranged[$i];

            if ($c >= '0' && $c <= '9') {
                $remainder = ($remainder * 10 + (int) $c) % 97;
            } else {
                $n = ord($c) - ord('A') + 10;
                $remainder = ($remainder * 10 + intdiv($n, 10)) % 97;
                $remainder = ($remainder * 10 + $n % 10) % 97;
            }
        }

        return $remainder === 1;
    }

    /**
     * The error to report for a value that failed validate(): the catalog
     * error key and the {expected} placeholder override.
     *
     * @return array{string, string|null}
     */
    public function error(mixed $value): array
    {
        return [ErrorMessages::IBAN, null];
    }
}
