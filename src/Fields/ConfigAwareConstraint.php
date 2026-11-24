<?php

declare(strict_types=1);

namespace Attributes\Strictify\Fields;

use Attributes\Strictify\ModelConfigs;

interface ConfigAwareConstraint extends Constraint
{
    public function setModelConfigs(ModelConfigs $configs): void;
}
