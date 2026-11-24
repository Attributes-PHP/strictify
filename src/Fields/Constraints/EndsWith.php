<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;
use Attributes\Strictify\Internal\ErrorMessages;
use Attributes\Strictify\Internal\Value;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class EndsWith implements Field
{
    public array $values;

    public function __construct(string|int|float ...$values)
    {
        $this->values = $values;
    }

    public function validate(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        foreach ($this->values as $suffix) {
            $suffixString = (string) $suffix;
            $length = strlen($suffixString);

            if ($length <= strlen($value) && substr_compare($value, $suffixString, -$length) === 0) {
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
        return [ErrorMessages::ENDS_WITH, Value::listString($this->values)];
    }
}
