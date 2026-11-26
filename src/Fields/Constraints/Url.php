<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;
use Attributes\Strictify\Internal\ErrorMessages;
use Attributes\Strictify\Internal\Value;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class Url implements Field
{
    private const PATTERN = '~^[A-Za-z][A-Za-z0-9+.-]*:\\/\\/[^\\s]+$~';

    /** Allowed schemes (the part before ://): a string or a list of strings; null allows any */
    public string|array|null $protocol;

    /** Allowed hosts: a string or a list of strings; null allows any */
    public string|array|null $domain;

    public function __construct(string|array|null $protocol = null, string|array|null $domain = null)
    {
        $this->protocol = self::readListArg($protocol, 1, 'protocol');
        $this->domain = self::readListArg($domain, 2, 'domain');
    }

    public function validate(mixed $value): bool
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            return false;
        }

        $checkProtocol = $this->protocol !== null;
        $checkDomain = $this->domain !== null;

        if (! $checkProtocol && ! $checkDomain) {
            return true;
        }

        // The format above guarantees a scheme followed by "://"
        $scheme = substr($value, 0, (int) strpos($value, '://'));

        if ($checkProtocol && ! self::matches($this->protocol, $scheme, true)) {
            return false;
        }

        if ($checkDomain) {
            $authority = substr($value, (int) strpos($value, '://') + 3);
            $authorityEnd = strlen($authority);

            foreach (['/', '?', '#'] as $delimiter) {
                $pos = strpos($authority, $delimiter);

                if ($pos !== false && $pos < $authorityEnd) {
                    $authorityEnd = $pos;
                }
            }

            $authority = substr($authority, 0, $authorityEnd);

            // userinfo@host: the host starts after the last '@'
            $at = strrpos($authority, '@');
            $host = $at === false ? $authority : substr($authority, $at + 1);

            // strip the port
            $colon = strpos($host, ':');

            if ($colon !== false) {
                $host = substr($host, 0, $colon);
            }

            if (! self::matches($this->domain, $host, false)) {
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
        if (is_string($value) && preg_match(self::PATTERN, $value) === 1) {
            $scheme = substr($value, 0, (int) strpos($value, '://'));

            if ($this->protocol !== null && ! self::matches($this->protocol, $scheme, true)) {
                $allowed = is_array($this->protocol) ? $this->protocol : [$this->protocol];

                return [ErrorMessages::URL_PROTOCOL, Value::listString($allowed)];
            }

            if ($this->domain !== null) {
                $authority = substr($value, (int) strpos($value, '://') + 3);
                $authorityEnd = strlen($authority);

                foreach (['/', '?', '#'] as $delimiter) {
                    $pos = strpos($authority, $delimiter);

                    if ($pos !== false && $pos < $authorityEnd) {
                        $authorityEnd = $pos;
                    }
                }

                $authority = substr($authority, 0, $authorityEnd);
                $at = strrpos($authority, '@');
                $host = $at === false ? $authority : substr($authority, $at + 1);
                $colon = strpos($host, ':');

                if ($colon !== false) {
                    $host = substr($host, 0, $colon);
                }

                if (! self::matches($this->domain, $host, false)) {
                    $allowed = is_array($this->domain) ? $this->domain : [$this->domain];

                    return [ErrorMessages::URL_DOMAIN, Value::listString($allowed)];
                }
            }
        }

        return [ErrorMessages::URL, null];
    }

    /**
     * The compile-time argument rules: an empty string or an empty list
     * allows no protocol/host at all, so the constraint would fail every
     * input.
     */
    public function argError(): ?string
    {
        return self::allowedListError($this->protocol, 'protocol')
            ?? self::allowedListError($this->domain, 'domain');
    }

    /**
     * Case-insensitive match of a URL part against a declared string or
     * list. Protocol entries tolerate the separators people write:
     * 'https', 'https:' and 'https://' all mean the same scheme.
     */
    private static function matches(string|array $allowed, string $part, bool $trimSeparator): bool
    {
        foreach ((array) $allowed as $candidate) {
            if ($trimSeparator) {
                if (str_ends_with($candidate, '://')) {
                    $candidate = substr($candidate, 0, -3);
                } elseif (str_ends_with($candidate, ':')) {
                    $candidate = substr($candidate, 0, -1);
                }
            }

            if (strcasecmp($part, $candidate) === 0) {
                return true;
            }
        }

        return false;
    }

    private static function allowedListError(string|array|null $allowed, string $argument): ?string
    {
        if ($allowed === null) {
            return null;
        }

        if (is_string($allowed)) {
            return $allowed === '' ? sprintf('$%s must not be empty.', $argument) : null;
        }

        if ($allowed === []) {
            return sprintf('$%s must declare at least one entry.', $argument);
        }

        foreach ($allowed as $entry) {
            if ($entry === '') {
                return sprintf('$%s must not contain an empty string.', $argument);
            }
        }

        return null;
    }

    /**
     * Validates a string|array|null constructor argument: arrays must
     * hold only strings.
     */
    private static function readListArg(string|array|null $arg, int $argNum, string $name): string|array|null
    {
        if ($arg === null || is_string($arg)) {
            return $arg;
        }

        foreach ($arg as $entry) {
            if (! is_string($entry)) {
                throw new \TypeError(sprintf(
                    'Attributes\Strictify\Fields\Constraints\Url::__construct(): Argument #%d ($%s) must contain only strings, %s given',
                    $argNum,
                    $name,
                    Value::typeName($entry)
                ));
            }
        }

        return $arg;
    }
}
