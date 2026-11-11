<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Application\Presentation;

use Attribute;
use Phil\Enums\Meta\AbstractMetaProperty;

/**
 * The colour a badge shows the case in: a name the application's stylesheet knows — `green`, `warning` —, never a
 * CSS value, so that a theme can change it.
 *
 * @extends AbstractMetaProperty<string>
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final class Color extends AbstractMetaProperty
{
}
