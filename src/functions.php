<?php

declare(strict_types=1);

namespace Attributes\Strictify;

use ArrayAccess;
use Attributes\Strictify\Internal\Hydrator;

/**
 * Validates a raw data array against the type hints and attributes of a
 * BaseModel instance, and returns the hydrated model.
 *
 * @throws Exceptions\ValidationException when any field fails validation
 */
function validate(array $rawData, BaseModel $model): BaseModel
{
    return Hydrator::hydrate($rawData, $model);
}

/**
 * Calls a function or callable with validated models: BaseModel parameters
 * hydrate from the raw data array through the same engine validate() uses,
 * and every other parameter is injected from a dependencies array or
 * ArrayAccess container.
 */
function call(string|callable $function, array $params, mixed $dependencies = []): mixed
{
    if (! is_array($dependencies) && ! $dependencies instanceof ArrayAccess) {
        throw new \TypeError(sprintf(
            'Attributes\Strictify\call(): Argument #3 ($dependencies) must be of type array|ArrayAccess, %s given',
            Internal\Value::typeName($dependencies)
        ));
    }

    return Internal\Caller::call($function, $params, $dependencies);
}
