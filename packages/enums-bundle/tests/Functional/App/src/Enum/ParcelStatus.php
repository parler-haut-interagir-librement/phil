<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Functional\App\src\Enum;

use Phil\Enums\Attribute\Alias;
use Phil\Enums\Attribute\Description;
use Phil\Enums\Attribute\Group;
use Phil\Enums\Attribute\Label;
use Phil\Enums\Attribute\Meta;
use Phil\Enums\Attribute\TransitionsTo;
use Phil\Enums\Traits\AsMetadatableEnumTrait;
use Phil\EnumsBundle\Application\Presentation\Color;
use Phil\EnumsBundle\Infrastructure\Translation\AsTranslatableEnumTrait;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * The lifecycle of a parcel: translatable, grouped, with an alias, a colour and transitions — the enum the
 * application's `src/` declares, which the commands and the extractor find.
 */
#[Meta(Label::class, Description::class, Group::class)]
enum ParcelStatus: string implements TranslatableInterface
{
    use AsMetadatableEnumTrait;
    use AsTranslatableEnumTrait;

    #[Label('Ordered')] #[Description('Waiting to be shipped.')] #[Group('open')] #[Alias('new')] #[Color('grey')]
    #[TransitionsTo(self::Shipped, self::Cancelled)]
    case Ordered = 'ordered';

    #[Label('Shipped')] #[Group('open')]
    #[TransitionsTo(self::Delivered)]
    case Shipped = 'shipped';

    #[Label('Delivered')]
    case Delivered = 'delivered';

    #[Label('Cancelled')]
    case Cancelled = 'cancelled';
}
