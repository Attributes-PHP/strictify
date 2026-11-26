<?php

declare(strict_types=1);

namespace Attributes\Strictify;

use ArrayAccess;
use Attributes\Strictify\Internal\DepthGuard;
use Attributes\Strictify\Internal\Hydrator;

/**
 * Validates a raw data array against the type hints and attributes of a
 * BaseModel instance, and returns the hydrated model.
 *
 * $maxDepth caps how deeply the payload may nest models or array shapes
 * (default 512); past the limit a MaxDepthException is thrown instead of
 * exhausting the stack.
 *
 * @throws Exceptions\ValidationException when any field fails validation
 * @throws Exceptions\MaxDepthException when the payload nests too deeply
 */
function validate(array $rawData, BaseModel $model, int $maxDepth = DepthGuard::DEFAULT_LIMIT): BaseModel
{
    if ($maxDepth < 1) {
        throw new \ValueError(sprintf(
            'Argument #3 ($maxDepth) must be greater than 0, %d given',
            $maxDepth
        ));
    }

    return DepthGuard::withLimit($maxDepth, static fn (): BaseModel => Hydrator::hydrate($rawData, $model));
}

/**
 * Calls a function or callable with validated models: BaseModel parameters
 * hydrate from the raw data array through the same engine validate() uses,
 * and every other parameter is injected from a dependencies array or
 * ArrayAccess container.
 *
 * $maxDepth applies to the validation of the model arguments (default 512).
 *
 * @throws Exceptions\CallException when a required parameter has no value
 */
function call(string|callable $function, array $params, array|ArrayAccess $dependencies = [], int $maxDepth = DepthGuard::DEFAULT_LIMIT): mixed
{
    if ($maxDepth < 1) {
        throw new \ValueError(sprintf(
            'Argument #4 ($maxDepth) must be greater than 0, %d given',
            $maxDepth
        ));
    }

    return DepthGuard::withLimit($maxDepth, static fn (): mixed => Internal\Caller::call($function, $params, $dependencies));
}
