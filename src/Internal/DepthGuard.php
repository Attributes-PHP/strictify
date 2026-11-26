<?php

declare(strict_types=1);

namespace Attributes\Strictify\Internal;

use Attributes\Strictify\Exceptions\MaxDepthException;

/**
 * Nesting-depth guard for validation: nested models and nested array
 * shapes recurse, and each level consumes stack, so past the limit the
 * run stops with a catchable MaxDepthException instead of overflowing
 * the stack. The limit defaults to 512 and a validate()/call() run can
 * pass its own $maxDepth for its duration.
 *
 * @internal
 */
final class DepthGuard
{
    public const DEFAULT_LIMIT = 512;

    private static int $depth = 0;

    private static int $limit = self::DEFAULT_LIMIT;

    /**
     * Enters one nesting level, throwing past the limit.
     */
    public static function enter(): void
    {
        if (self::$depth >= self::$limit) {
            throw new MaxDepthException(sprintf(
                'Maximum validation nesting depth of %d exceeded: the payload nests models or arrays too deeply.',
                self::$limit
            ));
        }

        self::$depth++;
    }

    /**
     * Leaves one nesting level.
     */
    public static function leave(): void
    {
        self::$depth--;
    }

    /**
     * Runs $callback with $limit as the depth limit, restoring the
     * previous limit afterwards (also on exception).
     */
    public static function withLimit(int $limit, callable $callback): mixed
    {
        $previous = self::$limit;
        self::$limit = $limit;

        try {
            return $callback();
        } finally {
            self::$limit = $previous;
        }
    }
}
