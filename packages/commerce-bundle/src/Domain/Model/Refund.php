<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceBundle\Domain\Exception\PaymentRefused;
use Phil\CommerceBundle\Domain\Refund\RefundState;
use Phil\CommerceBundle\Domain\ValueObject\Identifier;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * A refund in two steps: granted for an amount of one payment, with its reason, then executed — the processor asked,
 * or an offline refund recorded — or withdrawn. Who granted and who executed are kept.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_refund')]
#[ORM\Index(columns: ['payment_id', 'state'])]
#[ORM\Index(columns: ['order_number'])]
final class Refund
{
    public Money $amount {
        get => Money::of($this->amountMinorUnits, Currency::of($this->currencyCode));
    }

    #[ORM\Column(name: 'state', length: 16, enumType: RefundState::class)]
    private RefundState $state = RefundState::Granted;

    #[ORM\Column(name: 'executed_by', length: 255, nullable: true)]
    private ?string $executedBy = null;

    #[ORM\Column(name: 'settled_at', nullable: true)]
    private ?DateTimeImmutable $settledAt = null;

    #[ORM\Version]
    #[ORM\Column(name: 'version', type: 'integer')]
    private int $version = 1;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'payment_id', length: 36, options: ['fixed' => true])]
        public readonly string $paymentId,
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        #[ORM\Column(name: 'amount', type: 'bigint')]
        private readonly int $amountMinorUnits,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currencyCode,
        #[ORM\Column(name: 'reason', length: 255)]
        public readonly string $reason,
        #[ORM\Column(name: 'granted_by', length: 255)]
        public readonly string $grantedBy,
        #[ORM\Column(name: 'granted_at')]
        public readonly DateTimeImmutable $grantedAt,
    ) {
    }

    public static function grant(Payment $payment, Money $amount, string $reason, string $grantedBy, DateTimeImmutable $at): self
    {
        if (!$amount->currency->equals($payment->amount->currency)) {
            throw PaymentRefused::currency($payment->id, $payment->amount->currency->code, $amount->currency->code);
        }

        if ('' === trim($reason) || mb_strlen($reason) > 255 || 1 !== preg_match('/^\P{Cc}+$/Du', $reason)) {
            throw InvalidValue::because('A refund has a reason: 1 to 255 characters on one line.');
        }

        return new self(Uuid::v7()->toRfc4122(), $payment->id, $payment->orderNumber, $amount->minorUnits, $amount->currency->code, $reason, Identifier::check($grantedBy, 'actor'), $at);
    }

    public function state(): RefundState
    {
        return $this->state;
    }

    public function executedBy(): ?string
    {
        return $this->executedBy;
    }

    public function settledAt(): ?DateTimeImmutable
    {
        return $this->settledAt;
    }

    public function execute(string $executedBy, DateTimeImmutable $at): void
    {
        $this->checkGranted();
        $this->state = RefundState::Executed;
        $this->executedBy = Identifier::check($executedBy, 'actor');
        $this->settledAt = $at;
    }

    public function withdraw(DateTimeImmutable $at): void
    {
        $this->checkGranted();
        $this->state = RefundState::Withdrawn;
        $this->settledAt = $at;
    }

    private function checkGranted(): void
    {
        if (RefundState::Granted !== $this->state) {
            throw PaymentRefused::refundSettled($this->id, $this->state->value);
        }
    }
}
