<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;
use Attributes\Strictify\Internal\ErrorMessages;
use Attributes\Strictify\Internal\Value;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class Regex implements Field
{
    public string $regex;

    public function __construct(string $regex)
    {
        $this->regex = $regex;
    }

    public function validate(mixed $value): bool
    {
        return is_string($value) && @preg_match($this->regex, $value) === 1;
    }

    /**
     * The error to report for a value that failed validate(): the catalog
     * error key and the {expected} placeholder override.
     *
     * @return array{string, string|null}
     */
    public function error(mixed $value): array
    {
        return [ErrorMessages::REGEX, Value::toString($this->regex)];
    }

    /**
     * The compile-time argument rule: the pattern must compile. The check
     * parses the delimiter and modifiers the way preg does and compiles
     * through preg with a temporary error handler, so an invalid pattern
     * is rejected here without a PHP warning escaping from validate().
     */
    public function argError(): ?string
    {
        return self::patternError($this->regex);
    }

    /**
     * The rejection reason for a preg pattern, or null when it compiles.
     */
    public static function patternError(string $pattern): ?string
    {
        $length = strlen($pattern);

        if ($length === 0) {
            return 'the pattern is empty.';
        }

        $delimiter = $pattern[0];

        if (ctype_alnum($delimiter) || $delimiter === '\\' || strpbrk($delimiter, " \t\n\r\v\f\0") !== false) {
            return 'the delimiter must not be alphanumeric, a backslash or whitespace.';
        }

        $closing = match ($delimiter) {
            '(' => ')',
            '[' => ']',
            '{' => '}',
            '<' => '>',
            default => $delimiter,
        };

        // The closing delimiter is the first unescaped occurrence, as in
        // preg; bracket-style delimiters nest and must be balanced
        $bodyEnd = null;
        $depth = 1;

        for ($i = 1; $i < $length; $i++) {
            if ($pattern[$i] === '\\') {
                $i++;

                continue;
            }

            if ($closing !== $delimiter && $pattern[$i] === $delimiter) {
                $depth++;

                continue;
            }

            if ($pattern[$i] !== $closing) {
                continue;
            }

            if ($closing !== $delimiter && --$depth > 0) {
                continue;
            }

            $bodyEnd = $i;

            break;
        }

        if ($bodyEnd === null) {
            return 'no ending delimiter was found.';
        }

        // Modifiers PHP accepts after the closing delimiter
        $seen = [];

        for ($i = $bodyEnd + 1; $i < $length; $i++) {
            $modifier = $pattern[$i];

            if (strpos('imsxADSUXJunr', $modifier) === false) {
                return sprintf("unknown modifier '%s'.", $modifier);
            }

            if (isset($seen[$modifier])) {
                return sprintf("duplicate modifier '%s'.", $modifier);
            }

            $seen[$modifier] = true;
        }

        $body = substr($pattern, 1, $bodyEnd - 1);

        if (str_contains($body, "\0")) {
            return 'the pattern contains a NUL byte.';
        }

        // Compile through preg with a temporary swallowing handler, so the
        // warning stays inside this check
        $warning = null;
        set_error_handler(static function (int $no, string $message) use (&$warning): bool {
            $warning = $message;

            return true;
        });

        try {
            $compiles = @preg_match($pattern, '') !== false;
        } finally {
            restore_error_handler();
        }

        if ($compiles) {
            return null;
        }

        $detail = $warning ?? '';

        foreach (['preg_match(): Compilation failed: ', 'preg_match(): '] as $prefix) {
            if (str_starts_with($detail, $prefix)) {
                $detail = substr($detail, strlen($prefix));

                break;
            }
        }

        return sprintf('the pattern does not compile: %s.', $detail);
    }
}
