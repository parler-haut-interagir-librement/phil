<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Application\Contract;

use Phil\CommerceCatalogBundle\Domain\Model\CommunityCopyClaim;
use Phil\CommerceCatalogBundle\Domain\Model\CommunityCopyFunding;

/**
 * Keeps what funded the community copies of each product and who claimed them.
 * `Infrastructure\Doctrine\DoctrineCommunityCopyLedger` is the Doctrine adapter.
 */
interface CommunityCopyLedger
{
    /**
     * What every order gave towards the product's copies, in its minor units.
     */
    public function funded(string $sku): int;

    /**
     * How many copies of the product were claimed.
     */
    public function claimed(string $sku): int;

    public function isFundedBy(string $sku, string $orderNumber): bool;

    public function hasClaimed(string $sku, string $claimant): bool;

    public function addFunding(CommunityCopyFunding $funding): void;

    public function addClaim(CommunityCopyClaim $claim): void;

    /**
     * Locks the product's row until the transaction ends, so that two claims of its last copy run one after the
     * other.
     */
    public function lock(string $sku): void;
}
