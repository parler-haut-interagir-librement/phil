<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture;

use Phil\Enums\Attribute\Label;
use Phil\Enums\Attribute\Meta;
use Phil\Enums\Tests\Fixture\Attribute\Shade;
use Phil\Enums\Traits\AsMetadatableEnumTrait;

/**
 * Meta properties from the enum (`Label`) and from a trait it uses (`Shade`, read as `tint()`).
 */
#[Meta(Label::class)]
enum ShadedEnum
{
    use AsMetadatableEnumTrait;
    use HasShade;

    #[Label('Dark')] #[Shade('black')]
    case DARK;
}
