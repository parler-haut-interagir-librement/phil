<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommercePosBundle\Domain\Session\CashMovementKind;
use Phil\Money\Currency;
use Phil\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * Cash into the drawer or out of it, positive when in; for a sale or a refund, also what rounding to the increment
 * changed — the cash counted less what the payment says. A movement is never changed nor removed.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_pos_cash_movement')]
#[ORM\Index(columns: ['session'])]
final class CashMovement
{
    public Money $amount {
        get => Money::of($this->amountMinorUnits, Currency::of($this->currencyCode));
    }

    public Money $rounding {
        get => Money::of($this->roundingMinorUnits, Currency::of($this->currencyCode));
    }

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'session', length: 36, options: ['fixed' => true])]
        public readonly string $session,
        #[ORM\Column(name: 'kind', length: 16, enumType: CashMovementKind::class)]
        public readonly CashMovementKind $kind,
        #[ORM\Column(name: 'amount', type: 'bigint')]
        private readonly int $amountMinorUnits,
        #[ORM\Column(name: 'rounding', type: 'integer')]
        private readonly int $roundingMinorUnits,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currencyCode,
        #[ORM\Column(name: 'by_whom', length: 255)]
        public readonly string $by,
        #[ORM\Column(name: 'occurred_at', type: 'datetime_utc_immutable')]
        public readonly DateTimeImmutable $occurredAt,
        #[ORM\Column(name: 'payment', length: 36, nullable: true, options: ['fixed' => true])]
        public readonly ?string $payment,
        #[ORM\Column(name: 'note', length: 255, nullable: true)]
        public readonly ?string $note,
    ) {
    }

    /**
     * Recorded by the session, which alone knows whether it is open.
     *
     * @internal
     */
    public static function record(CashSession $session, CashMovementKind $kind, Money $amount, Money $rounding, string $by, DateTimeImmutable $at, ?string $payment, ?string $note): self
    {
        return new self(Uuid::v7()->toRfc4122(), $session->id, $kind, $amount->minorUnits, $rounding->minorUnits, $amount->currency->code, $by, $at, $payment, null === $note ? null : mb_substr($note, 0, 255));
    }
}
