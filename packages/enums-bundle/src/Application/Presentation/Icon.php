<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Application\Presentation;

use Attribute;
use Phil\Enums\Meta\AbstractMetaProperty;

/**
 * The icon a badge shows before the case's label: a name the application's icon set knows.
 *
 * @extends AbstractMetaProperty<string>
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final class Icon extends AbstractMetaProperty
{
}
