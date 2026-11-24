<?php

declare(strict_types=1);

namespace Attributes\Strictify;

use Attributes\Strictify\Internal\ErrorMessages;

/**
 * Registers the callable used to translate validation error message
 * templates. The translator receives the stable error key (null for custom
 * #[ErrorMessage] values) and the default English template, and returns the
 * translated template or null to keep the default.
 */
final class ErrorTranslator
{
    /**
     * callable is not a valid property type, so the static holds mixed.
     *
     * @var callable|null
     */
    private static mixed $translator = null;

    public static function get(): ?callable
    {
        return self::$translator;
    }

    public static function set(mixed $translator): void
    {
        if (! is_callable($translator)) {
            throw new \TypeError('Argument 1 passed to Attributes\\Strictify\\ErrorTranslator::set() must be callable');
        }

        self::$translator = $translator;

        // Templates resolved with the previous translator are cached per
        // property: drop the cache so the new translator takes effect
        ErrorMessages::clearCache();
    }
}
