<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceCatalogBundle\Application\Contract\CommunityCopyLedger;
use Phil\CommerceCatalogBundle\Domain\Model\CommunityCopyClaim;
use Phil\CommerceCatalogBundle\Domain\Model\CommunityCopyFunding;

final class InMemoryCommunityCopies implements CommunityCopyLedger
{
    /** @var array<string, CommunityCopyFunding> */
    public array $fundings = [];

    /** @var array<string, CommunityCopyClaim> */
    public array $claims = [];

    /** @var list<string> */
    public array $locks = [];

    #[Override]
    public function funded(string $sku): int
    {
        return array_sum(array_map(static fn (CommunityCopyFunding $funding): int => $funding->sku === $sku ? $funding->amount : 0, $this->fundings));
    }

    #[Override]
    public function claimed(string $sku): int
    {
        return \count(array_filter($this->claims, static fn (CommunityCopyClaim $claim): bool => $claim->sku === $sku));
    }

    #[Override]
    public function isFundedBy(string $sku, string $orderNumber): bool
    {
        return isset($this->fundings[$sku."\0".$orderNumber]);
    }

    #[Override]
    public function hasClaimed(string $sku, string $claimant): bool
    {
        return isset($this->claims[$sku."\0".$claimant]);
    }

    #[Override]
    public function addFunding(CommunityCopyFunding $funding): void
    {
        $this->fundings[$funding->sku."\0".$funding->orderNumber] = $funding;
    }

    #[Override]
    public function addClaim(CommunityCopyClaim $claim): void
    {
        $this->claims[$claim->sku."\0".$claim->claimant] = $claim;
    }

    #[Override]
    public function lock(string $sku): void
    {
        $this->locks[] = $sku;
    }
}
