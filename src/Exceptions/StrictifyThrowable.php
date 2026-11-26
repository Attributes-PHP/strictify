<?php

declare(strict_types=1);

namespace Attributes\Strictify\Exceptions;

/**
 * Marker interface shared by every error the library raises: data
 * failures extend \Exception (like JsonException), programming errors
 * extend \Error (like TypeError and ValueError), and one catch of this
 * interface still covers them all.
 */
interface StrictifyThrowable extends \Throwable {}
