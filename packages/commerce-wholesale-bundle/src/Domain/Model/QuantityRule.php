<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceWholesaleBundle\Domain\Quantity\QuantityBreach;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * How many units of a SKU a line may hold: at least a minimum, at most a maximum when there is one, and only in steps
 * of an increment counted from the minimum — a minimum of 6 and an increment of 6 allow 6, 12, 18 and so on.
 *
 * A rule names a customer group, and holds for its buyers only, or names none, and holds for every buyer of a group
 * without a rule of its own.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_wholesale_quantity_rule')]
final class QuantityRule
{
    /** The stored group of a rule for every buyer, which a storage adapter looks it up by: a key column cannot be null. */
    public const string EVERY_GROUP = '';

    public ?string $customerGroup {
        get => self::EVERY_GROUP === $this->group ? null : $this->group;
    }

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'sku', length: 64)]
        public readonly string $sku,
        #[ORM\Id]
        #[ORM\Column(name: 'customer_group', length: 64)]
        private readonly string $group,
        #[ORM\Column(name: 'minimum', type: 'integer')]
        private int $minimum,
        #[ORM\Column(name: 'maximum', type: 'integer', nullable: true)]
        private ?int $maximum,
        #[ORM\Column(name: 'increment', type: 'integer')]
        private int $increment,
    ) {
    }

    /**
     * @param string|null $customerGroup the group the rule holds for; every group without a rule of its own when null
     * @param int|null    $maximum       no limit when null
     */
    public static function of(string $sku, ?string $customerGroup = null, int $minimum = 1, ?int $maximum = null, int $increment = 1): self
    {
        if (1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $sku)) {
            throw InvalidValue::because(\sprintf('"%s" is not a SKU: 1 to 64 visible ASCII characters.', $sku));
        }

        if (null !== $customerGroup && 1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $customerGroup)) {
            throw InvalidValue::because(\sprintf('"%s" is not a customer group: 1 to 64 visible ASCII characters.', $customerGroup));
        }

        self::check($sku, $minimum, $maximum, $increment);

        return new self($sku, $customerGroup ?? self::EVERY_GROUP, $minimum, $maximum, $increment);
    }

    public function minimum(): int
    {
        return $this->minimum;
    }

    public function maximum(): ?int
    {
        return $this->maximum;
    }

    public function increment(): int
    {
        return $this->increment;
    }

    public function change(int $minimum, ?int $maximum, int $increment): void
    {
        self::check($this->sku, $minimum, $maximum, $increment);
        $this->minimum = $minimum;
        $this->maximum = $maximum;
        $this->increment = $increment;
    }

    /**
     * How the quantity breaks the rule, the first of the minimum, the maximum and the increment it breaks; `null`
     * when the rule allows it.
     */
    public function breachOf(int $quantity): ?QuantityBreach
    {
        return match (true) {
            $quantity < $this->minimum => QuantityBreach::BelowMinimum,
            null !== $this->maximum && $quantity > $this->maximum => QuantityBreach::AboveMaximum,
            0 !== ($quantity - $this->minimum) % $this->increment => QuantityBreach::OffIncrement,
            default => null,
        };
    }

    /**
     * The closest quantity at or above the one given that the rule allows; `null` when the maximum stops it.
     */
    public function nextAllowed(int $quantity): ?int
    {
        $steps = $quantity <= $this->minimum ? 0 : intdiv($quantity - $this->minimum + $this->increment - 1, $this->increment);
        $allowed = $this->minimum + $steps * $this->increment;

        return null !== $this->maximum && $allowed > $this->maximum ? null : $allowed;
    }

    private static function check(string $sku, int $minimum, ?int $maximum, int $increment): void
    {
        if ($minimum < 1 || $increment < 1 || $minimum > 1_000_000 || $increment > 1_000_000) {
            throw InvalidValue::because(\sprintf('The quantities of %s start at 1 to 1000000 units, in steps of 1 to 1000000.', $sku));
        }

        if (null !== $maximum && ($maximum < $minimum || $maximum > 1_000_000_000)) {
            throw InvalidValue::because(\sprintf('The most units of %s a line holds is at least its minimum of %d, and at most 1000000000.', $sku, $minimum));
        }
    }
}
