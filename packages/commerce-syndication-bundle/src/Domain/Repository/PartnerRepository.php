<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Domain\Repository;

use Phil\CommerceSyndicationBundle\Domain\Model\Partner;

interface PartnerRepository
{
    public function findPartner(string $code): ?Partner;

    public function addPartner(Partner $partner): void;

    /**
     * The active resellers, which a product update may concern.
     *
     * @return list<Partner>
     */
    public function activeResellers(): array;

    /**
     * The active resellers fed this SKU, by code: the ones a product update concerns, found without reading every
     * reseller's list.
     *
     * @return list<Partner>
     */
    public function resellersFed(string $sku): array;
}
