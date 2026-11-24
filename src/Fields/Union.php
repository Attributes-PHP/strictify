<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields;

use Attributes\Strictify\Exceptions\InvalidBaseModelException;
use Attributes\Strictify\Internal\Masks;
use Attributes\Strictify\Internal\Value;
use Attributes\Strictify\Type;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class Union implements Field
{
    public array $of = [];

    public function __construct(mixed ...$of)
    {
        $arms = array_values($of);

        foreach ($arms as $index => $arm) {
            if (! (is_int($arm) || is_string($arm) || $arm instanceof Type
                || $arm instanceof Sequence || $arm instanceof Union || $arm instanceof Intersection || $arm instanceof Dict)) {
                throw new \TypeError(sprintf(
                    'Union::__construct(): Argument #%d must be of type int|string|\\Attributes\\Strictify\\Type|Sequence|Union|Intersection|Dict, %s given',
                    $index + 1,
                    Value::typeName($arm)
                ));
            }

            if (is_string($arm) && ! Value::isResolvableClassName($arm)) {
                throw new \ValueError(sprintf(
                    'Union::__construct(): Argument #%d must be a valid class, interface or enum, "%s" given',
                    $index + 1,
                    $arm
                ));
            }
        }

        $this->of = $arms;
    }

    /**
     * Validates a whole value against the arms when the Union is declared
     * directly on a non-array property: exact type match per arm, never a
     * coercion (the native type check already ran).
     *
     * @throws InvalidBaseModelException when the value is an array
     */
    public function validateScalar(mixed $value): bool
    {
        if (is_array($value)) {
            throw new InvalidBaseModelException('Union cannot be applied directly to array values; use Sequence(of: new Union(...)) or Dict(of: ...) instead.');
        }

        foreach ($this->of as $arm) {
            if ($this->armMatches($arm, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Renders the arm list like a union type: mask arms expand to their
     * basic type names, class arms render as written, intersection arms
     * join their class names with " and ", array-shape arms render as
     * "array"; parts are joined with ", " and a trailing " or ".
     */
    public function armsExpected(): string
    {
        $parts = [];

        foreach ($this->of as $arm) {
            if (is_string($arm)) {
                $parts[] = $arm;

                continue;
            }

            if ($arm instanceof Intersection) {
                $members = [];

                foreach ($arm->of as $class) {
                    if (is_string($class)) {
                        $members[] = $class;
                    }
                }

                $parts[] = $members === [] ? 'mixed' : implode(' and ', $members);

                continue;
            }

            if ($arm instanceof Type) {
                $arm = $arm->value;
            }

            if (is_int($arm)) {
                $parts[] = self::maskToString(self::normalizeMask($arm));

                continue;
            }

            // Sequence/Union/Dict nested-shape arm
            $parts[] = 'array';
        }

        $total = count($parts);

        if ($total === 0) {
            return 'mixed';
        }

        $result = '';

        foreach ($parts as $index => $part) {
            if ($index > 0) {
                $result .= $index + 1 === $total ? ' or ' : ', ';
            }

            $result .= $part;
        }

        return $result;
    }

    /**
     * Whether an arm matches a scalar value exactly (no coercion).
     */
    private function armMatches(mixed $arm, mixed $value): bool
    {
        if (is_int($arm) || $arm instanceof Type) {
            $mask = is_int($arm) ? $arm : $arm->value;

            return (Masks::of($value) & self::normalizeMask($mask)) !== 0;
        }

        if (is_string($arm)) {
            return is_object($value) && $value instanceof $arm;
        }

        if ($arm instanceof Intersection) {
            if (! is_object($value)) {
                return false;
            }

            foreach ($arm->of as $class) {
                if (! $value instanceof $class) {
                    return false;
                }
            }

            return true;
        }

        // Sequence/Union/Dict shape arms never match a scalar
        return false;
    }

    /**
     * Expands a type mask into its basic type names joined with ", "
     * ("mixed" when empty).
     */
    private static function maskToString(int $mask): string
    {
        $names = [];

        foreach (Masks::BASIC_NAMES as [$bit, $name]) {
            if (($mask & $bit) === $bit) {
                $names[] = $name;
            }
        }

        return $names === [] ? 'mixed' : implode(', ', $names);
    }

    /**
     * The dict bit folds into the array bit.
     */
    private static function normalizeMask(int $mask): int
    {
        if (($mask & Masks::DICT) !== 0) {
            $mask = ($mask & ~Masks::DICT) | Masks::ARRAY;
        }

        return $mask;
    }
}
