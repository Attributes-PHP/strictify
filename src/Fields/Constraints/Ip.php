<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;
use Attributes\Strictify\Internal\ErrorMessages;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class Ip implements Field
{
    public ?string $version;

    public function __construct(?string $version = null)
    {
        if ($version !== null && $version !== 'v4' && $version !== 'v6') {
            throw new \ValueError(sprintf(
                'Attributes\Strictify\Fields\Constraints\Ip::__construct(): Argument #1 ($version) must be "v4", "v6" or null, "%s" given',
                $version
            ));
        }

        $this->version = $version;
    }

    public function validate(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $wantV4 = $this->version === 'v4';
        $wantV6 = $this->version === 'v6';

        if (! $wantV6 && self::isIpv4($value)) {
            return true;
        }

        return ! $wantV4 && self::isIpv6($value);
    }

    /**
     * The error to report for a value that failed validate(): the catalog
     * error key and the {expected} placeholder override.
     *
     * @return array{string, string|null}
     */
    public function error(mixed $value): array
    {
        return [ErrorMessages::IP, null];
    }

    private static function isIpv4(string $s): bool
    {
        $len = strlen($s);
        $dots = 0;
        $partStart = 0;

        for ($i = 0; $i <= $len; $i++) {
            if ($i === $len || $s[$i] === '.') {
                $partLen = $i - $partStart;

                if ($partLen < 1 || $partLen > 3) {
                    return false;
                }

                if ($partLen > 1 && $s[$partStart] === '0') {
                    return false;
                }

                $octet = 0;

                for ($j = $partStart; $j < $i; $j++) {
                    if ($s[$j] < '0' || $s[$j] > '9') {
                        return false;
                    }

                    $octet = $octet * 10 + (ord($s[$j]) - ord('0'));
                }

                if ($octet > 255) {
                    return false;
                }

                if ($i === $len) {
                    break;
                }

                $dots++;
                $partStart = $i + 1;
            }
        }

        return $dots === 3;
    }

    private static function isIpv6(string $s): bool
    {
        $len = strlen($s);

        if ($len === 0 || $len > 45) {
            return false;
        }

        $groups = 0;
        $compressed = false;
        $i = 0;

        if ($len >= 2 && $s[0] === ':' && $s[1] === ':') {
            $compressed = true;
            $i = 2;
        } elseif ($s[0] === ':') {
            return false;
        }

        while ($i < $len) {
            $start = $i;

            while ($i < $len && $s[$i] !== ':') {
                $i++;
            }

            $group = substr($s, $start, $i - $start);
            $hasDigit = strpbrk($group, '0123456789') !== false;
            $hasDot = str_contains($group, '.');

            if ($hasDot) {
                if (! $hasDigit || ! self::isIpv4($group)) {
                    return false;
                }

                $groups += 2;
            } else {
                if (! self::isHexGroup($group)) {
                    return false;
                }

                $groups++;
            }

            if ($i < $len) {
                if ($i + 1 < $len && $s[$i + 1] === ':') {
                    if ($compressed) {
                        return false;
                    }

                    $compressed = true;
                    $i += 2;

                    if ($i === $len) {
                        break; // trailing ::
                    }
                } else {
                    $i++;

                    if ($i === $len) {
                        return false; // trailing single colon
                    }
                }
            }
        }

        if ($compressed) {
            return $groups <= 7;
        }

        return $groups === 8;
    }

    private static function isHexGroup(string $s): bool
    {
        $len = strlen($s);

        if ($len < 1 || $len > 4) {
            return false;
        }

        return preg_match('~^[0-9A-Fa-f]+$~', $s) === 1;
    }
}
