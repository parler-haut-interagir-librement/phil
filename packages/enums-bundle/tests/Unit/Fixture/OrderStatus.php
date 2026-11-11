<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Fixture;

use Phil\Enums\Attribute\Description;
use Phil\Enums\Attribute\Group;
use Phil\Enums\Attribute\Label;
use Phil\Enums\Attribute\Meta;
use Phil\Enums\Traits\AsMetadatableEnumTrait;
use Phil\EnumsBundle\Infrastructure\Translation\AsTranslatableEnumTrait;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * A translatable enum with labels, descriptions and groups; `REFUNDED` carries no label and no description.
 */
#[Meta(Label::class, Description::class, Group::class, Priority::class)]
enum OrderStatus: string implements TranslatableInterface
{
    use AsMetadatableEnumTrait;
    use AsTranslatableEnumTrait;

    #[Label('Pending')] #[Description('Waiting for payment.')] #[Group('customer')] #[Group('staff')] #[Priority(1)]
    case PENDING = 'pending';

    #[Label('In transit')] #[Description('The parcel has left.')] #[Group('customer')] #[Priority(2)]
    case IN_TRANSIT = 'in_transit';

    #[Label('Cancelled')] #[Group('staff')] #[Priority(2)]
    case CANCELLED = 'cancelled';

    case REFUNDED = 'refunded';
}
