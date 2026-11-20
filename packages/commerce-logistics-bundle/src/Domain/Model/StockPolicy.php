<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * How one SKU behaves when its stock runs out: refused, by default, or sold without stock — a backorder, with neither
 * a threshold nor a ceiling. The units reserved beyond what is on hand leave once goods are received.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_logistics_stock_policy')]
final class StockPolicy
{
    #[ORM\Column(name: 'backorders')]
    private bool $backorders = false;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'sku', length: 64)]
        public readonly string $sku,
    ) {
    }

    public static function of(string $sku): self
    {
        if (1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $sku)) {
            throw InvalidValue::because(\sprintf('"%s" is not a SKU: 1 to 64 visible ASCII characters.', $sku));
        }

        return new self($sku);
    }

    public function allowsBackorders(): bool
    {
        return $this->backorders;
    }

    public function allowBackorders(): void
    {
        $this->backorders = true;
    }

    public function forbidBackorders(): void
    {
        $this->backorders = false;
    }
}
