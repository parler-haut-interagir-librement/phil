<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\Model;

use DateTimeImmutable;
use Phil\NewsletterBundle\Domain\Enum\MembershipStatus;

/**
 * A subscriber's place on one list.
 */
final class Membership
{
    public function __construct(
        public readonly string $subscriberId,
        public readonly string $listId,
        public private(set) MembershipStatus $status,
        public private(set) DateTimeImmutable $changedAt,
    ) {
    }

    public function activate(DateTimeImmutable $now): void
    {
        $this->status = MembershipStatus::Active;
        $this->changedAt = $now;
    }

    public function leave(DateTimeImmutable $now): void
    {
        $this->status = MembershipStatus::Unsubscribed;
        $this->changedAt = $now;
    }

    /**
     * The subscription goes no further: the confirmation was not sent, since the domain of the address receives no
     * mail. Subscribing again starts a new confirmation.
     */
    public function refuse(DateTimeImmutable $now): void
    {
        $this->status = MembershipStatus::Refused;
        $this->changedAt = $now;
    }

    public function isActive(): bool
    {
        return MembershipStatus::Active === $this->status;
    }
}
