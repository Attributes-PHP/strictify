<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;
use Attributes\Strictify\Internal\ErrorMessages;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class PrimeNumber implements Field
{
    public function validate(mixed $value): bool
    {
        if (! is_int($value) && ! is_float($value)) {
            return false;
        }

        return self::isPrime(is_int($value) ? $value : (int) $value);
    }

    /**
     * The error to report for a value that failed validate(): the catalog
     * error key and the {expected} placeholder override.
     *
     * @return array{string, string|null}
     */
    public function error(mixed $value): array
    {
        return [ErrorMessages::PRIME_NUMBER, null];
    }

    private static function isPrime(int $n): bool
    {
        if ($n < 2) {
            return false;
        }

        if ($n < 4) {
            return true;
        }

        if ($n % 2 === 0) {
            return false;
        }

        for ($d = 3; $d * $d <= $n; $d += 2) {
            if ($n % $d === 0) {
                return false;
            }
        }

        return true;
    }
}
