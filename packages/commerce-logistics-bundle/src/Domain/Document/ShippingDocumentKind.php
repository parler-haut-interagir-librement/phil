<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Document;

/**
 * The documents the module declares to the core's register of document types, each numbered in its own series.
 */
enum ShippingDocumentKind: string
{
    /** Travels with a shipment: what is in the parcel, the gift message, the prices unless hidden. */
    case DeliveryNote = 'delivery_note';

    /** Guides whoever prepares an order: which units to take from which location. */
    case PickingSlip = 'picking_slip';
}
