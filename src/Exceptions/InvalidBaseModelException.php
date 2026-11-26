<?php

declare(strict_types=1);

namespace Attributes\Strictify\Exceptions;

/**
 * Thrown when a model declaration is malformed: an attribute argument
 * the engine or a constructor rejects, an invalid attribute placement,
 * or a cross-field rule violation. A programming error, so it extends
 * \Error like TypeError and ValueError.
 */
class InvalidBaseModelException extends \Error implements StrictifyThrowable {}
