<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\StableIdentifier;

/**
 * A member of an organization holding one of the seats of the organization's subscription.
 *
 * When the offer counts fewer seats than are held — a change to a smaller tier —, the earliest seats keep their
 * features and the later ones wait, until seats are released or added.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_subscription_seat')]
#[ORM\Index(columns: ['member'])]
final class Seat
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'subscription', length: 36, options: ['fixed' => true])]
        public readonly string $subscriptionId,
        #[ORM\Id]
        #[ORM\Column(name: 'member', length: 255)]
        public readonly string $member,
        #[ORM\Column(name: 'assigned_at')]
        public readonly DateTimeImmutable $assignedAt,
    ) {
    }

    public static function assign(Subscription $subscription, StableIdentifier $member, DateTimeImmutable $at): self
    {
        return new self($subscription->id, $member->value, $at);
    }
}
