<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceStoredValueBundle\Domain\Exception\StoredValueRefused;
use Phil\CommerceStoredValueBundle\Domain\Instrument\GiftCardOrigin;
use Phil\CommerceStoredValueBundle\Domain\Instrument\InstrumentKind;
use Phil\CommerceStoredValueBundle\Domain\Instrument\MovementKind;
use Phil\CommerceStoredValueBundle\Domain\ValueObject\GiftCardCode;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * Stored value in one currency: a gift card, known by the hash of its code, or a customer's store credit, known by
 * the customer. Its balance moves only through movements, each returned to be stored, and never goes below zero.
 *
 * A gift card is given or paid for. A given card may expire; a paid card never does, unless it is for a specific
 * service and marked so. **Whether even that is allowed is the law's**, which each shop checks where it sells. A voided
 * instrument loses what it held, and is used no more.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_stored_value_instrument')]
#[ORM\UniqueConstraint(columns: ['code_hash'])]
#[ORM\UniqueConstraint(columns: ['holder', 'currency'])]
final class Instrument
{
    public Money $balance {
        get => Money::of($this->balanceMinorUnits, Currency::of($this->currencyCode));
    }

    #[ORM\Column(name: 'balance', type: 'bigint')]
    private int $balanceMinorUnits = 0;

    #[ORM\Column(name: 'active')]
    private bool $active = true;

    #[ORM\Version]
    #[ORM\Column(name: 'version', type: 'integer')]
    private int $version = 1;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'kind', length: 16, enumType: InstrumentKind::class)]
        public readonly InstrumentKind $kind,
        #[ORM\Column(name: 'code_hash', length: 64, nullable: true, options: ['fixed' => true])]
        public readonly ?string $codeHash,
        #[ORM\Column(name: 'last_four', length: 4, nullable: true, options: ['fixed' => true])]
        public readonly ?string $lastFour,
        #[ORM\Column(name: 'holder', length: 255, nullable: true)]
        public readonly ?string $holder,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currencyCode,
        #[ORM\Column(name: 'opened_at')]
        public readonly DateTimeImmutable $openedAt,
        #[ORM\Column(name: 'expires_at', nullable: true)]
        public readonly ?DateTimeImmutable $expiresAt,
        #[ORM\Column(name: 'origin', length: 16, nullable: true, enumType: GiftCardOrigin::class)]
        public readonly ?GiftCardOrigin $origin = null,
        #[ORM\Column(name: 'specific_service', options: ['default' => false])]
        public readonly bool $specificService = false,
    ) {
    }

    /**
     * A new gift card and its first value; the code is shown once, to whoever receives the card. A card is paid for
     * unless said given; a paid card takes an expiry only when it is for a specific service.
     *
     * @return array{self, Movement}
     */
    public static function giftCard(GiftCardCode $code, Money $value, DateTimeImmutable $at, ?DateTimeImmutable $expiresAt = null, ?string $orderNumber = null, GiftCardOrigin $origin = GiftCardOrigin::Paid, bool $specificService = false): array
    {
        if ($expiresAt instanceof DateTimeImmutable) {
            if ($expiresAt <= $at) {
                throw InvalidValue::because('A gift card expires after it is issued.');
            }

            if (GiftCardOrigin::Paid === $origin && !$specificService) {
                throw InvalidValue::because('A paid gift card does not expire, unless it is for a specific service.');
            }
        }

        $card = new self(Uuid::v7()->toRfc4122(), InstrumentKind::GiftCard, $code->hash(), $code->lastFour(), null, $value->currency->code, $at, $expiresAt, $origin, $specificService);

        return [$card, $card->credit(MovementKind::Issued, $value, $at, $orderNumber)];
    }

    /**
     * A customer's store credit in one currency, empty until credit is granted.
     */
    public static function storeCredit(string $holder, Currency $currency, DateTimeImmutable $at): self
    {
        if ('' === trim($holder) || mb_strlen($holder) > 255) {
            throw InvalidValue::because('A store credit belongs to a customer, named in 1 to 255 characters.');
        }

        return new self(Uuid::v7()->toRfc4122(), InstrumentKind::StoreCredit, null, null, $holder, $currency->code, $at, null);
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function hasExpired(DateTimeImmutable $at): bool
    {
        return $this->expiresAt instanceof DateTimeImmutable && $this->expiresAt <= $at;
    }

    /**
     * Adds value: a gift card loaded, credit granted, a refund restored.
     */
    public function credit(MovementKind $kind, Money $amount, DateTimeImmutable $at, ?string $orderNumber = null, ?string $payment = null, ?string $note = null): Movement
    {
        if (!$kind->credits()) {
            throw InvalidValue::because(\sprintf('A "%s" movement takes value away.', $kind->value));
        }

        $this->checkUsable($amount, $at);
        $this->balanceMinorUnits += $amount->minorUnits;

        return Movement::record($this, $kind, $amount, $at, $orderNumber, $payment, $note);
    }

    /**
     * Whether value can be added now: the instrument is active and has not expired.
     */
    public function canTakeValue(DateTimeImmutable $at): bool
    {
        return $this->active && !$this->hasExpired($at);
    }

    /**
     * Refuses what `redeem()` would refuse — an amount that is not positive or in another currency, a voided or expired
     * instrument, more than the balance —, without changing anything: a payment checks it before it exists.
     *
     * @throws InvalidValue
     * @throws StoredValueRefused
     */
    public function assertRedeemable(Money $amount, DateTimeImmutable $at): void
    {
        $this->checkUsable($amount, $at);

        if ($amount->isGreaterThan($this->balance)) {
            throw StoredValueRefused::insufficient($this->id, $amount->toDecimal(), $this->balance->toDecimal());
        }
    }

    /**
     * Spends value on an order's payment.
     */
    public function redeem(Money $amount, DateTimeImmutable $at, string $orderNumber, string $payment): Movement
    {
        $this->assertRedeemable($amount, $at);
        $this->balanceMinorUnits -= $amount->minorUnits;

        return Movement::record($this, MovementKind::Redeemed, $amount->negate(), $at, $orderNumber, $payment, null);
    }

    /**
     * Takes away what is left and closes the instrument for good.
     */
    public function void(DateTimeImmutable $at, ?string $note = null): Movement
    {
        if (!$this->active) {
            throw StoredValueRefused::inactive($this->id);
        }

        $left = $this->balance;
        $this->balanceMinorUnits = 0;
        $this->active = false;

        return Movement::record($this, MovementKind::Voided, $left->negate(), $at, null, null, $note);
    }

    /**
     * The instrument's own state first — voided, expired —, which tells the customer more than the amount does.
     */
    private function checkUsable(Money $amount, DateTimeImmutable $at): void
    {
        if (!$this->active) {
            throw StoredValueRefused::inactive($this->id);
        }

        if ($this->hasExpired($at)) {
            throw StoredValueRefused::expired($this->id);
        }

        if (!$amount->isPositive()) {
            throw InvalidValue::because('A stored value moves by a positive amount.');
        }

        if (!$amount->currency->equals(Currency::of($this->currencyCode))) {
            throw InvalidValue::because(\sprintf('The stored value %s is in %s, not in %s.', $this->id, $this->currencyCode, $amount->currency->code));
        }
    }
}
