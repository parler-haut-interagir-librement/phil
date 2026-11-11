<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Fixture;

use Deprecated;
use Phil\Enums\Attribute\DefaultCase;
use Phil\Enums\Attribute\Description;
use Phil\Enums\Attribute\Group;
use Phil\Enums\Attribute\Label;
use Phil\Enums\Attribute\Meta;
use Phil\Enums\Attribute\Weight;
use Phil\Enums\Traits\AsMetadatableEnumTrait;
use Phil\EnumsBundle\Application\Presentation\Color;
use Phil\EnumsBundle\Application\Presentation\Icon;

/**
 * Bit flags that are not translatable: a default, groups, a colour and an icon on `Read`, and a deprecated `Admin`,
 * read through `from()` in the tests so that no test triggers its deprecation.
 */
#[Meta(Label::class, Group::class)]
enum Access: int
{
    use AsMetadatableEnumTrait;

    #[Label('Read')] #[Group('basic')] #[Color('green')] #[Icon('eye')] #[DefaultCase]
    case Read = 1;

    #[Label('Write')] #[Group('basic')] #[Group('editor')]
    case Write = 2;

    #[Label('Admin')] #[Description('Every right.')] #[Weight(0)]
    #[Deprecated]
    case Admin = 4;
}
