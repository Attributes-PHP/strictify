<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields\Constraints;

use Attributes\Strictify\Fields\Field;
use Attributes\Strictify\Internal\ErrorMessages;
use Attributes\Strictify\Internal\Value;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class Contains implements Field
{
    public array $values;

    public function __construct(string|int|float ...$values)
    {
        if ($values === []) {
            throw new \ArgumentCountError('Attributes\Strictify\Fields\Constraints\Contains::__construct() expects at least 1 argument, 0 given');
        }

        $this->values = $values;
    }

    public function validate(mixed $value): bool
    {
        if (is_string($value)) {
            foreach ($this->values as $needle) {
                if (! str_contains($value, (string) $needle)) {
                    return false;
                }
            }

            return true;
        }

        if (is_array($value)) {
            foreach ($this->values as $needle) {
                $found = false;

                foreach ($value as $element) {
                    if ($needle === $element) {
                        $found = true;

                        break;
                    }
                }

                if (! $found) {
                    return false;
                }
            }

            return true;
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
        return [ErrorMessages::CONTAINS, Value::listString($this->values)];
    }
}
