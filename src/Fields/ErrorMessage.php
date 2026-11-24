<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class ErrorMessage implements Field
{
    public function __construct(
        public readonly string $required = 'Field is required',
        public readonly string $type = 'Must be {expected}',
        public readonly string $alpha_dash = 'Must only contain letters, numbers, dashes and underscores',
        public readonly string $alpha_dash_type = 'Must be a string containing only letters, numbers, dashes and underscores',
        public readonly string $ascii = 'Must only contain ASCII characters',
        public readonly string $ascii_type = 'Must be a string containing only ASCII characters',
        public readonly string $between = 'Must be between {expected}',
        public readonly string $between_type = 'Must be a number between {expected}',
        public readonly string $contains = 'Must contain {expected}',
        public readonly string $datetime = 'Must be a datetime in the format {expected}',
        public readonly string $ends_with = 'Must end with one of: {expected}',
        public readonly string $in = 'Must be one of: {expected}',
        public readonly string $max = 'Must be at most {expected}',
        public readonly string $min = 'Must be at least {expected}',
        public readonly string $multiple_of = 'Must be a multiple of {expected}',
        public readonly string $regex = 'Must match the pattern {expected}',
        public readonly string $size = 'Must have a size {expected}',
        public readonly string $starts_with = 'Must start with one of: {expected}',
        public readonly string $ulid = 'Must be a valid ULID',
        public readonly string $uuid = 'Must be a valid UUID',
        public readonly string $union = 'Must be {expected}',
        public readonly string $sequence_keys = 'Must be a sequential list (keys 0, 1, 2, ...)',
    ) {}
}
