<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;
use Attributes\Strictify\Internal\ErrorMessages;
use Attributes\Strictify\Internal\Value;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class DateTime implements Field
{
    public readonly string $format;

    public readonly \DateTimeZone|string|null $timezone;

    /**
     * @internal
     */
    private ?\DateTimeZone $timezoneObj = null;

    public function __construct(string $format = 'X-m-d\\TH:i:sP', \DateTimeZone|string|null $timezone = null)
    {
        if (func_num_args() > 2) {
            throw new \ArgumentCountError(sprintf('%s::__construct() expects at most 2 arguments, %d given', self::class, func_num_args()));
        }

        if ($timezone instanceof \DateTimeZone) {
            try {
                $timezone->getName();
            } catch (\Error) {
                throw new \ValueError('Argument #2 ($timezone) must be a constructed DateTimeZone object');
            }

            $this->timezoneObj = $timezone;
        } elseif (is_string($timezone)) {
            try {
                $this->timezoneObj = new \DateTimeZone($timezone);
            } catch (\Exception) {
                throw new \ValueError(sprintf('Argument #2 ($timezone) must be a valid timezone identifier, \'%s\' given', $timezone));
            }
        }

        $this->format = $format;
        $this->timezone = $timezone;
    }

    public function validate(mixed $value): bool
    {
        if ($value instanceof \DateTimeInterface) {
            return true;
        }

        if (is_string($value)) {
            $parsed = $this->timezoneObj === null
                ? date_create_from_format($this->format, $value)
                : date_create_from_format($this->format, $value, $this->timezoneObj);

            if ($parsed === false) {
                return false;
            }

            // A value that parses but does not describe a real date
            // (1994-02-30, month 13, April 31) is reported by the date
            // extension as a warning and silently rolled over into the
            // next month: treat it as invalid. Lenient-but-real input
            // (the unpadded 1994-1-1) produces no warning and keeps
            // passing.
            $lastErrors = \DateTime::getLastErrors();

            return ! is_array($lastErrors) || ($lastErrors['warning_count'] ?? 0) === 0;
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
        return [ErrorMessages::DATETIME, Value::toString($this->format)];
    }

    /**
     * @internal
     */
    public function getTimezoneObj(): ?\DateTimeZone
    {
        return $this->timezoneObj;
    }
}
