<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Domain\Repository;

use Phil\CommerceSyndicationBundle\Domain\Model\PartnerReturn;

interface PartnerReturnRepository
{
    public function findReturn(string $partner, string $returnNumber): ?PartnerReturn;

    public function addReturn(PartnerReturn $return): void;

    /**
     * @return list<PartnerReturn> the returns of the order with the partner, oldest first
     */
    public function returnsOf(string $partner, string $orderNumber): array;
}
