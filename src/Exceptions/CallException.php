<?php

declare(strict_types=1);

namespace Attributes\Strictify\Exceptions;

/**
 * Thrown by call() when a required parameter or dependency has no
 * value. An argument-count failure, so it extends \ArgumentCountError.
 */
class CallException extends \ArgumentCountError implements StrictifyThrowable {}
