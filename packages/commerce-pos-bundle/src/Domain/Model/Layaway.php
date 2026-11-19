<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommercePosBundle\Domain\Exception\PosRefused;
use Phil\CommercePosBundle\Domain\Layaway\LayawayState;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * Goods put aside for a customer on a deposit, paid in instalments — the order's payments — until a due date. Paid in
 * full, the layaway is completed and the goods go home; given up, it is cancelled.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_pos_layaway')]
#[ORM\Index(columns: ['state', 'due_at'])]
final class Layaway
{
    #[ORM\Column(name: 'state', length: 16, enumType: LayawayState::class)]
    private LayawayState $state = LayawayState::Open;

    #[ORM\Column(name: 'ended_at', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $endedAt = null;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        #[ORM\Column(name: 'customer', length: 255)]
        public readonly string $customer,
        #[ORM\Column(name: 'opened_at', type: 'datetime_utc_immutable')]
        public readonly DateTimeImmutable $openedAt,
        #[ORM\Column(name: 'due_at', type: 'datetime_utc_immutable')]
        public readonly DateTimeImmutable $dueAt,
    ) {
    }

    public static function of(string $orderNumber, string $customer, DateTimeImmutable $at, DateTimeImmutable $dueAt): self
    {
        if ('' === trim($customer) || mb_strlen($customer) > 255) {
            throw InvalidValue::because('A layaway belongs to a customer, named in 1 to 255 characters.');
        }

        if ($dueAt <= $at) {
            throw InvalidValue::because('A layaway is due after it is opened.');
        }

        return new self($orderNumber, $customer, $at, $dueAt);
    }

    public function state(): LayawayState
    {
        return $this->state;
    }

    public function endedAt(): ?DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function isOverdue(DateTimeImmutable $at): bool
    {
        return LayawayState::Open === $this->state && $this->dueAt <= $at;
    }

    public function complete(DateTimeImmutable $at): void
    {
        $this->end(LayawayState::Completed, $at);
    }

    public function cancel(DateTimeImmutable $at): void
    {
        $this->end(LayawayState::Cancelled, $at);
    }

    private function end(LayawayState $state, DateTimeImmutable $at): void
    {
        if (LayawayState::Open !== $this->state) {
            throw PosRefused::notALayaway($this->orderNumber);
        }

        $this->state = $state;
        $this->endedAt = $at;
    }
}
