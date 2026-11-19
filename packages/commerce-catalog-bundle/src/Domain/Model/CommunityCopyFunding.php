<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * What one order gave towards the community copies of one product, in the product's minor units: one row per product
 * and order, so that an order counted twice funds once.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_catalog_community_copy_funding')]
final class CommunityCopyFunding
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'sku', length: 64)]
        public readonly string $sku,
        #[ORM\Id]
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        #[ORM\Column(name: 'amount', type: 'bigint')]
        public readonly int $amount,
        #[ORM\Column(name: 'funded_at', type: 'datetime_utc_immutable')]
        public readonly DateTimeImmutable $fundedAt,
    ) {
    }

    public static function of(string $sku, string $orderNumber, int $amount, DateTimeImmutable $at): self
    {
        if ($amount < 1) {
            throw InvalidValue::because(\sprintf('The order %s funds the community copies of %s with more than zero.', $orderNumber, $sku));
        }

        return new self($sku, $orderNumber, $amount, $at);
    }
}
