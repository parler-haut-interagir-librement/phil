<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceStoredValueBundle\Domain\Instrument\MovementKind;
use Phil\Money\Currency;
use Phil\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * One line of a balance's history: why it moved, by how much — positive when added —, what it came to, and the order
 * and the payment it concerns. A movement is never changed nor removed.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_stored_value_movement')]
#[ORM\Index(columns: ['instrument'])]
#[ORM\Index(columns: ['payment'])]
#[ORM\Index(columns: ['order_number'])]
final class Movement
{
    public Money $amount {
        get => Money::of($this->amountMinorUnits, Currency::of($this->currencyCode));
    }

    public Money $balanceAfter {
        get => Money::of($this->balanceAfterMinorUnits, Currency::of($this->currencyCode));
    }

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'instrument', length: 36, options: ['fixed' => true])]
        public readonly string $instrument,
        #[ORM\Column(name: 'kind', length: 16, enumType: MovementKind::class)]
        public readonly MovementKind $kind,
        #[ORM\Column(name: 'amount', type: 'bigint')]
        private readonly int $amountMinorUnits,
        #[ORM\Column(name: 'balance_after', type: 'bigint')]
        private readonly int $balanceAfterMinorUnits,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currencyCode,
        #[ORM\Column(name: 'occurred_at')]
        public readonly DateTimeImmutable $occurredAt,
        #[ORM\Column(name: 'order_number', length: 64, nullable: true)]
        public readonly ?string $orderNumber,
        #[ORM\Column(name: 'payment', length: 36, nullable: true, options: ['fixed' => true])]
        public readonly ?string $payment,
        #[ORM\Column(name: 'note', length: 255, nullable: true)]
        public readonly ?string $note,
    ) {
    }

    /**
     * Recorded by the instrument, which alone knows the balance it leaves.
     *
     * @internal
     */
    public static function record(Instrument $instrument, MovementKind $kind, Money $amount, DateTimeImmutable $at, ?string $orderNumber, ?string $payment, ?string $note): self
    {
        return new self(
            Uuid::v7()->toRfc4122(),
            $instrument->id,
            $kind,
            $amount->minorUnits,
            $instrument->balance->minorUnits,
            $amount->currency->code,
            $at,
            $orderNumber,
            $payment,
            null === $note ? null : mb_substr($note, 0, 255),
        );
    }
}
