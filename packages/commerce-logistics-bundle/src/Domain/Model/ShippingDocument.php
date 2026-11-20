<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceLogisticsBundle\Domain\Document\ShippingDocumentKind;

/**
 * The number a delivery note or a picking slip was issued under, from the core's register: one delivery note per
 * shipment, one picking slip per order. Issuing again gives back the same number.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_logistics_document')]
#[ORM\UniqueConstraint(columns: ['kind', 'order_number', 'shipment'])]
#[ORM\Index(columns: ['order_number'])]
final class ShippingDocument
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'number', length: 32)]
        public readonly string $number,
        #[ORM\Column(name: 'kind', length: 16, enumType: ShippingDocumentKind::class)]
        public readonly ShippingDocumentKind $kind,
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        #[ORM\Column(name: 'shipment', length: 36)]
        private readonly string $shipmentId,
        #[ORM\Column(name: 'issued_at')]
        public readonly DateTimeImmutable $issuedAt,
    ) {
    }

    public static function deliveryNote(string $number, Shipment $shipment, DateTimeImmutable $at): self
    {
        return new self($number, ShippingDocumentKind::DeliveryNote, $shipment->orderNumber, $shipment->id, $at);
    }

    public static function pickingSlip(string $number, string $orderNumber, DateTimeImmutable $at): self
    {
        return new self($number, ShippingDocumentKind::PickingSlip, $orderNumber, '', $at);
    }

    /**
     * The shipment a delivery note travels with; none for a picking slip.
     */
    public function shipment(): ?string
    {
        return '' === $this->shipmentId ? null : $this->shipmentId;
    }
}
