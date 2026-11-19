<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * A payment taken at a register, in a session, whatever its method — cash, a card on the terminal, a gift card —,
 * and the terminal's approval when there is one: what the session's report totals by method.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_pos_payment')]
#[ORM\Index(columns: ['session'])]
final class SessionPayment
{
    public Money $amount {
        get => Money::of($this->amountMinorUnits, Currency::of($this->currencyCode));
    }

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'payment', length: 36, options: ['fixed' => true])]
        public readonly string $payment,
        #[ORM\Column(name: 'session', length: 36, options: ['fixed' => true])]
        public readonly string $session,
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        #[ORM\Column(name: 'method', length: 32)]
        public readonly string $method,
        #[ORM\Column(name: 'amount', type: 'bigint')]
        private readonly int $amountMinorUnits,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currencyCode,
        #[ORM\Column(name: 'approval', length: 255, nullable: true)]
        public readonly ?string $approval,
        #[ORM\Column(name: 'taken_at', type: 'datetime_utc_immutable')]
        public readonly DateTimeImmutable $takenAt,
    ) {
    }

    public static function of(string $payment, CashSession $session, string $orderNumber, string $method, Money $amount, ?string $approval, DateTimeImmutable $at): self
    {
        return new self($payment, $session->id, $orderNumber, $method, $amount->minorUnits, $amount->currency->code, $approval, $at);
    }
}
