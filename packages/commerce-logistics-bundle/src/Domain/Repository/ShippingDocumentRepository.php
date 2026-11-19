<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Repository;

use Phil\CommerceLogisticsBundle\Domain\Document\ShippingDocumentKind;
use Phil\CommerceLogisticsBundle\Domain\Model\ShippingDocument;

/**
 * Finds and stores the numbers of delivery notes and picking slips. `Infrastructure\Doctrine\DoctrineLogisticsRepository`
 * is the Doctrine adapter.
 */
interface ShippingDocumentRepository
{
    /**
     * @param string|null $shipment the shipment of a delivery note; none for a picking slip
     */
    public function findDocument(ShippingDocumentKind $kind, string $orderNumber, ?string $shipment): ?ShippingDocument;

    public function addDocument(ShippingDocument $document): void;
}
