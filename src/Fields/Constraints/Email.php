<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;
use Attributes\Strictify\Internal\ErrorMessages;
use Attributes\Strictify\Internal\Value;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class Email implements Field
{
    private const PATTERN = '~^[A-Za-z0-9.!#$%&\'*+\\/?^_`{|}\\~-]+@[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)+$~';

    /** Allowed domains after the @: a string or a list of strings; null allows any */
    public string|array|null $domain;

    public function __construct(string|array|null $domain = null)
    {
        if ($domain !== null) {
            $allowed = is_array($domain) ? $domain : [$domain];

            foreach ($allowed as $entry) {
                if (! is_string($entry)) {
                    throw new \TypeError(sprintf(
                        'Attributes\Strictify\Fields\Constraints\Email::__construct(): Argument #1 ($domain) must contain only strings, %s given',
                        Value::typeName($entry)
                    ));
                }
            }
        }

        $this->domain = $domain;
    }

    public function validate(mixed $value): bool
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            return false;
        }

        // The last label is the TLD: IANA delegates only labels of two or
        // more characters, and #[Domain] rejects one-letter TLDs already
        $lastDot = strrpos($value, '.');

        if ($lastDot === false || strlen($value) - $lastDot - 1 < 2) {
            return false;
        }

        if ($this->domain === null) {
            return true;
        }

        // The format above allows exactly one '@', so the first one ends
        // the local part
        $valueDomain = substr($value, (int) strpos($value, '@') + 1);

        $allowed = is_array($this->domain) ? $this->domain : [$this->domain];

        foreach ($allowed as $candidate) {
            if (strcasecmp($valueDomain, $candidate) === 0) {
                return true;
            }
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
        if (is_string($value) && preg_match(self::PATTERN, $value) === 1
            && (strrpos($value, '.') !== false && strlen($value) - strrpos($value, '.') - 1 >= 2)
            && $this->domain !== null) {
            $allowed = is_array($this->domain) ? $this->domain : [$this->domain];

            return [ErrorMessages::EMAIL_DOMAIN, Value::listString($allowed)];
        }

        return [ErrorMessages::EMAIL, null];
    }

    /**
     * The compile-time argument rule: an empty string or an empty list
     * allows no domain at all, so the constraint would fail every input.
     */
    public function argError(): ?string
    {
        return self::allowedListError($this->domain, 'domain');
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
}
