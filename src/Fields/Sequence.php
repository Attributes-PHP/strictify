<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields;

use Attributes\Strictify\Internal\Value;
use Attributes\Strictify\Type;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
readonly class Sequence implements Field
{
    public mixed $of;

    public bool $uniqueValues;

    public bool $sequential;

    /** 'asc', 'ASC', 'desc', 'DESC' to sort the validated values, or null to keep the input order */
    public ?string $sorted;

    public function __construct(
        mixed $of,
        bool $uniqueValues = false,
        bool $sequential = true,
        ?string $sorted = null,
    ) {
        if (! (is_int($of) || is_string($of) || $of instanceof Type
            || $of instanceof Sequence || $of instanceof Union || $of instanceof Intersection || $of instanceof Dict)) {
            throw new \TypeError(sprintf(
                'Sequence::__construct(): Argument #1 ($of) must be of type int|string|\\Attributes\\Strictify\\Type|Sequence|Union|Intersection|Dict, %s given',
                Value::typeName($of)
            ));
        }

        if (is_string($of) && ! Value::isResolvableClassName($of)) {
            throw new \ValueError(sprintf(
                'Sequence::__construct(): Argument #1 ($of) must be a valid class, interface or enum, "%s" given',
                $of
            ));
        }

        if ($sorted !== null && ! in_array(strtolower($sorted), ['asc', 'desc'], true)) {
            throw new \ValueError(sprintf(
                'Sequence::__construct(): Argument #4 ($sorted) must be "asc", "ASC", "desc", "DESC" or null, "%s" given',
                $sorted
            ));
        }

        $this->of = $of;
        $this->uniqueValues = $uniqueValues;
        $this->sequential = $sequential;
        $this->sorted = $sorted;
    }
}
