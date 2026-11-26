<?php

declare(strict_types=1);

namespace Attributes\Strictify\Exceptions;

/**
 * Thrown when a payload nests models or array shapes deeper than the
 * $maxDepth limit of validate()/call(). Past the limit the run stops
 * with this catchable exception instead of exhausting the stack.
 */
class MaxDepthException extends \Error implements StrictifyThrowable {}
