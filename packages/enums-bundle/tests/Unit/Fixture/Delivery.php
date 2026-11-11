<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Fixture;

use Phil\Enums\Attribute\Alias;
use Phil\Enums\Attribute\Group;
use Phil\Enums\Attribute\Meta;
use Phil\Enums\Attribute\TransitionsTo;
use Phil\EnumsBundle\Application\Presentation\Color;

/**
 * A lifecycle declared by transitions, with an alias and a colour: what the workflow, the route resolver and the
 * expression functions read.
 */
#[Meta(Group::class)]
enum Delivery: string
{
    #[TransitionsTo(self::Shipped, self::Cancelled)] #[Alias('new')] #[Group('open')] #[Color('grey')]
    case Ordered = 'ordered';

    #[TransitionsTo(self::Delivered)] #[Group('open')]
    case Shipped = 'shipped';

    case Delivered = 'delivered';

    case Cancelled = 'cancelled';
}
