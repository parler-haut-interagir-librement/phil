<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * A purchase on account, due on a date: what it came to, what the buyer paid of it, what was credited back — a
 * refund —, and so what is still outstanding; and what a refund owes back to the buyer beyond that, until the shop
 * settles it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_wholesale_receivable')]
#[ORM\Index(columns: ['buyer'])]
#[ORM\Index(columns: ['payment'])]
final class Receivable
{
    public Money $amount {
        get => Money::of($this->amountMinorUnits, Currency::of($this->currencyCode));
    }

    #[ORM\Column(name: 'paid', type: 'bigint')]
    private int $paidMinorUnits = 0;

    #[ORM\Column(name: 'credited', type: 'bigint')]
    private int $creditedMinorUnits = 0;

    #[ORM\Column(name: 'refund_due', type: 'bigint')]
    private int $refundDueMinorUnits = 0;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'buyer', length: 255)]
        public readonly string $buyer,
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        #[ORM\Column(name: 'payment', length: 36, options: ['fixed' => true])]
        public readonly string $payment,
        #[ORM\Column(name: 'amount', type: 'bigint')]
        private readonly int $amountMinorUnits,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currencyCode,
        #[ORM\Column(name: 'issued_at')]
        public readonly DateTimeImmutable $issuedAt,
        #[ORM\Column(name: 'due_at')]
        public readonly DateTimeImmutable $dueAt,
    ) {
    }

    public static function of(CreditAccount $account, string $orderNumber, string $payment, Money $amount, DateTimeImmutable $at): self
    {
        return new self(Uuid::v7()->toRfc4122(), $account->buyer, $orderNumber, $payment, $amount->minorUnits, $amount->currency->code, $at, $at->modify(\sprintf('+%d days', $account->termsDays())));
    }

    public function paid(): Money
    {
        return Money::of($this->paidMinorUnits, Currency::of($this->currencyCode));
    }

    public function credited(): Money
    {
        return Money::of($this->creditedMinorUnits, Currency::of($this->currencyCode));
    }

    public function outstanding(): Money
    {
        return Money::of($this->amountMinorUnits - $this->paidMinorUnits - $this->creditedMinorUnits, Currency::of($this->currencyCode));
    }

    /**
     * What refunds owe back to the buyer beyond what was outstanding — money the buyer already paid —, less what the
     * shop settled.
     */
    public function refundDue(): Money
    {
        return Money::of($this->refundDueMinorUnits, Currency::of($this->currencyCode));
    }

    public function isOverdue(DateTimeImmutable $at): bool
    {
        return $this->outstanding()->isPositive() && $this->dueAt < $at;
    }

    /**
     * Records a payment of the buyer, up to what is outstanding; the part taken is returned.
     */
    public function pay(Money $amount): Money
    {
        $taken = $this->taken($amount);
        $this->paidMinorUnits += $taken->minorUnits;

        return $taken;
    }

    /**
     * Credits back part of the purchase, up to what is outstanding; the part taken is returned.
     */
    public function credit(Money $amount): Money
    {
        $taken = $this->taken($amount);
        $this->creditedMinorUnits += $taken->minorUnits;

        return $taken;
    }

    /**
     * Refunds part of the purchase: what is outstanding is credited back first; the rest, which the buyer already
     * paid, is owed back to the buyer (`refundDue()`).
     *
     * @return Money the part credited against what is outstanding
     */
    public function refund(Money $amount): Money
    {
        $credited = $this->credit($amount);
        $this->refundDueMinorUnits += $amount->minorUnits - $credited->minorUnits;

        return $credited;
    }

    /**
     * Records that the shop gave back part of what it owes the buyer, up to what is due; the part taken is returned.
     */
    public function settleRefund(Money $amount): Money
    {
        $this->checkAmount($amount);
        $taken = $amount->isGreaterThan($this->refundDue()) ? $this->refundDue() : $amount;
        $this->refundDueMinorUnits -= $taken->minorUnits;

        return $taken;
    }

    private function taken(Money $amount): Money
    {
        $this->checkAmount($amount);

        return $amount->isGreaterThan($this->outstanding()) ? $this->outstanding() : $amount;
    }

    private function checkAmount(Money $amount): void
    {
        if (!$amount->isPositive() || !$amount->currency->equals(Currency::of($this->currencyCode))) {
            throw InvalidValue::because(\sprintf('An amount on the receivable %s is positive, in %s.', $this->id, $this->currencyCode));
        }
    }
}
