<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Contract\DocumentTypes;
use Phil\CommerceBundle\Domain\ValueObject\DocumentType;
use Phil\CommerceLogisticsBundle\Domain\Document\ShippingDocumentKind;

/**
 * The module's document types in the core's register: the delivery note and the picking slip, each numbered in its
 * own series, with the prefixes and digits of `phil_commerce_logistics.documents`.
 */
final readonly class LogisticsDocumentTypes implements DocumentTypes
{
    public function __construct(
        private string $deliveryNotePrefix = 'DN-',
        private string $pickingSlipPrefix = 'PS-',
        private int $digits = 8,
    ) {
    }

    #[Override]
    public function documentTypes(): iterable
    {
        yield DocumentType::of(ShippingDocumentKind::DeliveryNote->value, $this->deliveryNotePrefix, $this->digits);
        yield DocumentType::of(ShippingDocumentKind::PickingSlip->value, $this->pickingSlipPrefix, $this->digits);
    }
}
