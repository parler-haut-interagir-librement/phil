<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Fixture;

use Phil\Enums\Attribute\Description;
use Phil\Enums\Attribute\Label;
use Phil\Enums\Attribute\Meta;
use Phil\Enums\Traits\AsMetadatableEnumTrait;

/**
 * A pure enum that is not translatable: its labels are shown as written. `Blank` carries an empty label and an
 * empty description, `Éclair` a name outside ASCII.
 */
#[Meta(Label::class)]
enum Shape
{
    use AsMetadatableEnumTrait;

    #[Label('Round')]
    case Circle;

    case BigSquare;

    #[Label('')] #[Description('')]
    case Blank;

    case Éclair;
}
