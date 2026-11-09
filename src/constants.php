<?php

declare(strict_types=1);

/*
 * The Attributes\Strictify\{bool,int,float,string,sequence,dict,object}
 * constants hold the matching Type case values, so attribute arguments can
 * use the bare names (#[Union(bool, int, string)]) and combine them with the
 * bitwise OR operator (#[Union(bool | int | string)]) through const imports.
 */

namespace Attributes\Strictify;

if (! defined('Attributes\Strictify\bool')) {
    define('Attributes\Strictify\bool', Type::bool->value);
}

if (! defined('Attributes\Strictify\int')) {
    define('Attributes\Strictify\int', Type::int->value);
}

if (! defined('Attributes\Strictify\float')) {
    define('Attributes\Strictify\float', Type::float->value);
}

if (! defined('Attributes\Strictify\string')) {
    define('Attributes\Strictify\string', Type::string->value);
}

if (! defined('Attributes\Strictify\sequence')) {
    define('Attributes\Strictify\sequence', Type::sequence->value);
}

if (! defined('Attributes\Strictify\dict')) {
    define('Attributes\Strictify\dict', Type::dict->value);
}

if (! defined('Attributes\Strictify\object')) {
    define('Attributes\Strictify\object', Type::object->value);
}
