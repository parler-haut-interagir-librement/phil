<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture;

use Phil\Enums\Attribute\Label;
use Phil\Enums\Attribute\Meta;
use Phil\Enums\Tests\Fixture\Attribute\Caption;
use Phil\Enums\Traits\AsMetadatableEnumTrait;

/**
 * Two enabled meta properties with the same accessor: the first one listed answers it.
 */
#[Meta(Label::class, Caption::class)]
enum CaptionedEnum
{
    use AsMetadatableEnumTrait;

    #[Label('From the label')] #[Caption('From the caption')]
    case ONLY;
}
