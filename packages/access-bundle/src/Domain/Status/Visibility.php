<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Domain\Status;

/**
 * Who sees an item, besides those it is shared with. The owner always sees their own item.
 */
enum Visibility: string
{
    /** Everyone, signed in or not. */
    case Everyone = 'everyone';

    /** The members of the item's space, and its admins. */
    case Members = 'members';

    /** The admins of the item's space. */
    case Admins = 'admins';

    /** The owner alone. */
    case Owner = 'owner';

    private const int VISITOR = 1;

    private const int MEMBER = 2;

    private const int ADMIN = 4;

    /**
     * What a link between two items may show: the narrowest of their two visibilities.
     */
    public function linking(self $other): self
    {
        return match ($this->audience() & $other->audience()) {
            self::VISITOR | self::MEMBER | self::ADMIN => self::Everyone,
            self::MEMBER | self::ADMIN => self::Members,
            self::ADMIN => self::Admins,
            default => self::Owner,
        };
    }

    /**
     * The audiences besides the owner, as bits.
     */
    private function audience(): int
    {
        return match ($this) {
            self::Everyone => self::VISITOR | self::MEMBER | self::ADMIN,
            self::Members => self::MEMBER | self::ADMIN,
            self::Admins => self::ADMIN,
            self::Owner => 0,
        };
    }
}
