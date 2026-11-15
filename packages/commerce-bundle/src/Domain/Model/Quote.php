<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceBundle\Domain\Exception\QuoteRefused;
use Phil\CommerceBundle\Domain\Order\QuoteState;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * An order offered as a quote, to a customer, until a date: its order is frozen while the quote is pending, placed
 * at the quoted prices when it is accepted, a cart again when it is declined.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_quote')]
final class Quote
{
    #[ORM\Column(name: 'state', length: 16, enumType: QuoteState::class)]
    private QuoteState $state = QuoteState::Pending;

    #[ORM\Column(name: 'answered_at', nullable: true)]
    private ?DateTimeImmutable $answeredAt = null;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        #[ORM\Column(name: 'customer', length: 255)]
        public readonly string $customer,
        #[ORM\Column(name: 'issued_at')]
        public readonly DateTimeImmutable $issuedAt,
        #[ORM\Column(name: 'valid_until')]
        public readonly DateTimeImmutable $validUntil,
    ) {
    }

    public static function of(Order $order, string $customer, DateTimeImmutable $at, DateTimeImmutable $validUntil): self
    {
        if ('' === trim($customer) || mb_strlen($customer) > 255) {
            throw InvalidValue::because('A quote is offered to a customer, named in 1 to 255 characters.');
        }

        if ($validUntil <= $at) {
            throw InvalidValue::because('A quote is valid after it is issued.');
        }

        return new self($order->number, $customer, $at, $validUntil);
    }

    public function state(): QuoteState
    {
        return $this->state;
    }

    public function answeredAt(): ?DateTimeImmutable
    {
        return $this->answeredAt;
    }

    public function hasExpired(DateTimeImmutable $at): bool
    {
        return $this->validUntil < $at;
    }

    public function accept(DateTimeImmutable $at): void
    {
        $this->checkPending();

        if ($this->hasExpired($at)) {
            throw QuoteRefused::expired($this->orderNumber);
        }

        $this->state = QuoteState::Accepted;
        $this->answeredAt = $at;
    }

    public function decline(DateTimeImmutable $at): void
    {
        $this->checkPending();
        $this->state = QuoteState::Declined;
        $this->answeredAt = $at;
    }

    private function checkPending(): void
    {
        if (QuoteState::Pending !== $this->state) {
            throw QuoteRefused::notPending($this->orderNumber);
        }
    }
}
