<?php

declare(strict_types=1);

/*
 * The Attributes\Strictify\{T_BOOL,T_INT,T_FLOAT,T_STRING,T_NULL,T_SEQUENCE,T_DICT,T_OBJECT}
 * constants hold the matching Type case values, so attribute arguments can
 * use them (#[Union(T_BOOL, T_INT, T_STRING)]) and combine them with the
 * bitwise OR operator (#[Union(T_BOOL | T_INT | T_STRING)]) through const
 * imports. The legacy lowercase names hold the same values.
 */

namespace Attributes\Strictify;

if (! defined('Attributes\\Strictify\\T_BOOL')) {
    define('Attributes\\Strictify\\T_BOOL', Type::bool->value);
}

if (! defined('Attributes\\Strictify\\T_INT')) {
    define('Attributes\\Strictify\\T_INT', Type::int->value);
}

if (! defined('Attributes\\Strictify\\T_FLOAT')) {
    define('Attributes\\Strictify\\T_FLOAT', Type::float->value);
}

if (! defined('Attributes\\Strictify\\T_STRING')) {
    define('Attributes\\Strictify\\T_STRING', Type::string->value);
}

if (! defined('Attributes\\Strictify\\T_NULL')) {
    define('Attributes\\Strictify\\T_NULL', Type::null->value);
}

if (! defined('Attributes\\Strictify\\T_SEQUENCE')) {
    define('Attributes\\Strictify\\T_SEQUENCE', Type::sequence->value);
}

if (! defined('Attributes\\Strictify\\T_DICT')) {
    define('Attributes\\Strictify\\T_DICT', Type::dict->value);
}

if (! defined('Attributes\\Strictify\\T_OBJECT')) {
    define('Attributes\\Strictify\\T_OBJECT', Type::object->value);
}

if (! defined('Attributes\\Strictify\\bool')) {
    define('Attributes\\Strictify\\bool', Type::bool->value);
}

if (! defined('Attributes\\Strictify\\int')) {
    define('Attributes\\Strictify\\int', Type::int->value);
}

if (! defined('Attributes\\Strictify\\float')) {
    define('Attributes\\Strictify\\float', Type::float->value);
}

if (! defined('Attributes\\Strictify\\string')) {
    define('Attributes\\Strictify\\string', Type::string->value);
}

if (! defined('Attributes\\Strictify\\sequence')) {
    define('Attributes\\Strictify\\sequence', Type::sequence->value);
}

if (! defined('Attributes\\Strictify\\dict')) {
    define('Attributes\\Strictify\\dict', Type::dict->value);
}

if (! defined('Attributes\\Strictify\\object')) {
    define('Attributes\\Strictify\\object', Type::object->value);
}
