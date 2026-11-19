<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceLogisticsBundle\Domain\Delivery\DeliveryKind;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * A way an order reaches its buyer: what it costs, from which amount it is free, the zones it serves, how many days
 * it takes, and, for a pickup, where.
 *
 * Its zones are named as the order's tax zone is — the buyer's province or country, as the application names them —;
 * an empty list serves every zone.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_logistics_delivery_method')]
final class DeliveryMethod
{
    public Money $price {
        get => Money::of($this->priceMinorUnits, Currency::of($this->currencyCode));
    }

    #[ORM\Column(name: 'active')]
    private bool $active = true;

    /**
     * @param list<string> $zones
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'code', length: 64)]
        public readonly string $code,
        #[ORM\Column(name: 'label', length: 255)]
        public readonly string $label,
        #[ORM\Column(name: 'kind', length: 16, enumType: DeliveryKind::class)]
        public readonly DeliveryKind $kind,
        #[ORM\Column(name: 'price', type: 'bigint')]
        private readonly int $priceMinorUnits,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currencyCode,
        #[ORM\Column(name: 'free_from', type: 'bigint', nullable: true)]
        private readonly ?int $freeFromMinorUnits,
        #[ORM\Column(name: 'zones', type: 'json')]
        public readonly array $zones,
        #[ORM\Column(name: 'min_days', type: 'smallint')]
        public readonly int $minDays,
        #[ORM\Column(name: 'max_days', type: 'smallint')]
        public readonly int $maxDays,
        #[ORM\Column(name: 'pickup_location', length: 64, nullable: true)]
        public readonly ?string $pickupLocation,
        #[ORM\Column(name: 'priority', type: 'integer')]
        public readonly int $priority,
    ) {
    }

    /**
     * @param list<string> $zones    the zones served; every zone when empty
     * @param Money|null   $freeFrom free when what the lines are worth, after their promotions, reaches it
     */
    public static function of(
        string $code,
        string $label,
        DeliveryKind $kind,
        Money $price,
        ?Money $freeFrom = null,
        array $zones = [],
        int $minDays = 0,
        int $maxDays = 0,
        ?string $pickupLocation = null,
        int $priority = 0,
    ): self {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', $code)) {
            throw InvalidValue::because(\sprintf('"%s" is not a delivery method code: up to 64 lowercase letters, digits, "_", "." or "-".', $code));
        }

        if ('' === trim($label)) {
            throw InvalidValue::because(\sprintf('The delivery method %s has a label, which the receipt shows.', $code));
        }

        if ($price->isNegative() || ($freeFrom instanceof Money && (!$freeFrom->currency->equals($price->currency) || $freeFrom->isNegative()))) {
            throw InvalidValue::because(\sprintf('The delivery method %s costs zero or more, and is free from an amount of its currency.', $code));
        }

        foreach ($zones as $zone) {
            if (1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $zone)) {
                throw InvalidValue::because(\sprintf('"%s" is not a zone: 1 to 64 visible ASCII characters.', $zone));
            }
        }

        if ($minDays < 0 || $maxDays < $minDays || $maxDays > 365) {
            throw InvalidValue::because(\sprintf('The delivery method %s takes 0 to 365 days, its least before its most.', $code));
        }

        if ((DeliveryKind::Pickup === $kind) !== (null !== $pickupLocation)) {
            throw InvalidValue::because(\sprintf('A pickup names its location, and only a pickup does: %s.', $code));
        }

        return new self($code, $label, $kind, $price->minorUnits, $price->currency->code, $freeFrom?->minorUnits, array_values(array_unique($zones)), $minDays, $maxDays, $pickupLocation, $priority);
    }

    public function freeFrom(): ?Money
    {
        return null === $this->freeFromMinorUnits ? null : Money::of($this->freeFromMinorUnits, Currency::of($this->currencyCode));
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function withdraw(): void
    {
        $this->active = false;
    }

    public function restore(): void
    {
        $this->active = true;
    }

    public function serves(?string $zone, Currency $currency): bool
    {
        return $this->active && $currency->code === $this->currencyCode && ([] === $this->zones || \in_array($zone, $this->zones, true));
    }

    /**
     * What delivering an order worth `$worth` costs: its price, or nothing from its threshold.
     */
    public function costFor(Money $worth): Money
    {
        $price = $this->price;
        $freeFrom = $this->freeFrom();

        return $freeFrom instanceof Money && !$worth->isLessThan($freeFrom) ? Money::zero($price->currency) : $price;
    }
}
