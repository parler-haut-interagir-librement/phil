<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * One SKU a reseller is fed, one row per reseller and SKU: the index a product update finds the resellers it concerns
 * with, in one query, without reading every reseller's whole list.
 *
 * The reseller's own list, `Partner::skus()`, stays the source; `Infrastructure\Doctrine\FedSkuIndex` keeps these rows
 * in step with it at each flush.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_syndication_fed_sku')]
#[ORM\Index(columns: ['sku'])]
final class FedSku
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'partner', length: 63)]
        public readonly string $partner,
        #[ORM\Id]
        #[ORM\Column(name: 'sku', length: 64)]
        public readonly string $sku,
    ) {
    }

    public static function of(Partner $reseller, string $sku): self
    {
        return new self($reseller->code, $sku);
    }
}
