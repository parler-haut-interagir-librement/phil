<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\ValueObject\Identifier;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Symfony\Component\Uid\Uuid;

/**
 * One line of an order's journal: a move along its transitions, or a forced move, with who made it and, for a forced
 * one, why. A line is written once and never changes.
 */
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'phil_commerce_order_state_change')]
#[ORM\Index(columns: ['order_number', 'occurred_at'])]
final class OrderStateChange
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        #[ORM\Column(name: 'from_state', length: 16, enumType: OrderState::class)]
        public readonly OrderState $from,
        #[ORM\Column(name: 'to_state', length: 16, enumType: OrderState::class)]
        public readonly OrderState $to,
        /** The transition taken; `null` for a forced move. */
        #[ORM\Column(name: 'transition', length: 32, nullable: true)]
        public readonly ?string $transition,
        #[ORM\Column(name: 'actor', length: 255, nullable: true)]
        public readonly ?string $actor,
        /** Why the move was forced; `null` for a transition. */
        #[ORM\Column(name: 'reason', length: 255, nullable: true)]
        public readonly ?string $reason,
        #[ORM\Column(name: 'occurred_at')]
        public readonly DateTimeImmutable $occurredAt,
    ) {
    }

    public static function transition(string $orderNumber, OrderState $from, OrderState $to, string $transition, ?string $actor, DateTimeImmutable $at): self
    {
        return new self(Uuid::v7()->toRfc4122(), $orderNumber, $from, $to, $transition, null === $actor ? null : Identifier::check($actor, 'actor'), null, $at);
    }

    public static function forced(string $orderNumber, OrderState $from, OrderState $to, string $actor, string $reason, DateTimeImmutable $at): self
    {
        if ('' === trim($reason) || mb_strlen($reason) > 255 || 1 !== preg_match('/^\P{Cc}+$/Du', $reason)) {
            throw InvalidValue::because('A forced move has a reason: 1 to 255 characters on one line.');
        }

        return new self(Uuid::v7()->toRfc4122(), $orderNumber, $from, $to, null, Identifier::check($actor, 'actor'), $reason, $at);
    }

    public function wasForced(): bool
    {
        return null === $this->transition;
    }
}
