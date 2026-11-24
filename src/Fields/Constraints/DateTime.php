<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;

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

    /**
     * @internal
     */
    public function getTimezoneObj(): ?\DateTimeZone
    {
        return $this->timezoneObj;
    }
}
