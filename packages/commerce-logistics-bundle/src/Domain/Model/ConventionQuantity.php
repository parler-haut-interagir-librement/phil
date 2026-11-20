<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * How many units of one SKU — a shirt in one size — the shop prepares for one convention. A SKU with a quantity at any
 * convention is a convention item: an order placed online takes it from stock only when no convention is near.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_logistics_convention_quantity')]
#[ORM\Index(columns: ['sku'])]
final class ConventionQuantity
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'convention', length: 64)]
        public readonly string $convention,
        #[ORM\Id]
        #[ORM\Column(name: 'sku', length: 64)]
        public readonly string $sku,
        #[ORM\Column(name: 'quantity', type: 'integer')]
        private int $quantity,
    ) {
    }

    public static function of(Convention $convention, string $sku, int $quantity): self
    {
        if (1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $sku)) {
            throw InvalidValue::because(\sprintf('"%s" is not a SKU: 1 to 64 visible ASCII characters.', $sku));
        }

        return new self($convention->code, $sku, self::checked($quantity));
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function change(int $quantity): void
    {
        $this->quantity = self::checked($quantity);
    }

    private static function checked(int $quantity): int
    {
        return $quantity < 0 ? throw InvalidValue::because(\sprintf('A convention is prepared zero units or more; %d is refused.', $quantity)) : $quantity;
    }
}
