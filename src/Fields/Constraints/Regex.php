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
}
