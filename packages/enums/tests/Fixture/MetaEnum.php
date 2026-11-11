<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture;

use Phil\Enums\Attribute\Description;
use Phil\Enums\Attribute\Label;
use Phil\Enums\Attribute\Meta;
use Phil\Enums\Tests\Fixture\Attribute\Color;
use Phil\Enums\Traits\AsMetadatableEnumTrait;

#[Meta(Description::class, Label::class, Color::class)]
enum MetaEnum: int
{
    use AsMetadatableEnumTrait;

    #[Description('The water is cold')] #[Label('Cold')] #[Color('blue')]
    case COLD = 0;

    #[Description('The water boils')] #[Label('Boiling')] #[Color('orange')]
    case BOILING = 1;

    // No attribute: every accessor answers its property's default value.
    case UNPLUGGED = 2;
}
