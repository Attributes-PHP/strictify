<?php

declare(strict_types=1);

namespace Attributes\Strictify\Internal;

/**
 * A property type hint compiled once: the combined basic mask of its
 * builtin arms, the resolved class arms with their DateTime/BaseModel
 * flags, the intersection members and the fast-path flags.
 *
 * @internal
 */
final class TypePlan
{
    public bool $untyped = false;

    public bool $mixed = false;

    public bool $iterable = false;

    public int $basicMask = 0;

    /**
     * Whether the hint allows null (a nullable single type or a union
     * with a null arm).
     */
    public bool $nullable = false;

    /**
     * Whether the hint is a union type (a type list: scalar unions,
     * class unions or DNF types).
     */
    public bool $isUnion = false;

    /**
     * Whether a union hint carries a DNF intersection arm.
     */
    public bool $hasDnfArm = false;

    /**
     * The resolved class arms in declaration order.
     *
     * @var list<array{class: string, dateTime: bool, baseModel: bool}>
     */
    public array $classes = [];

    /**
     * The class lists of the intersection arms.
     *
     * @var list<list<string>>
     */
    public array $intersections = [];

    public bool $singleBaseModel = false;
}
