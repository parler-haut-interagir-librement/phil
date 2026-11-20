<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\DocumentRegister;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\GiftOptions;
use Phil\CommerceLogisticsBundle\Application\Document\DeliveryNote;
use Phil\CommerceLogisticsBundle\Application\Document\DeliveryNoteLine;
use Phil\CommerceLogisticsBundle\Application\Document\PickingLine;
use Phil\CommerceLogisticsBundle\Application\Document\PickingSlip;
use Phil\CommerceLogisticsBundle\Domain\Document\ShippingDocumentKind;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\Shipment;
use Phil\CommerceLogisticsBundle\Domain\Model\ShippingDocument;
use Phil\CommerceLogisticsBundle\Domain\Repository\ShippingDocumentRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\StockRepository;
use Phil\CommerceLogisticsBundle\Domain\Stock\ReservationState;

/**
 * The delivery note of a shipment and the picking slip of an order, numbered by the core's register in the series of
 * `LogisticsDocumentTypes`. A document is numbered once: issuing it again gives back its number and moment, with what
 * the order and its reservations hold now. A new number is drawn inside the transaction that stores the document, as
 * the register asks; the application flushes.
 *
 * The gift options of the order travel with the goods: the delivery note prints the wrapping and the message and
 * leaves the prices off when they are hidden; the picking slip says whether to wrap, and in which wrap.
 */
final readonly class ShippingDocuments
{
    public function __construct(
        private DocumentRegister $register,
        private ShippingDocumentRepository $documents,
        private StockRepository $stock,
    ) {
    }

    public function deliveryNote(Order $order, Shipment $shipment, DateTimeImmutable $at): DeliveryNote
    {
        if ($shipment->orderNumber !== $order->number) {
            throw StockRefused::notInOrder($order->number, $shipment->id);
        }

        $document = $this->documents->findDocument(ShippingDocumentKind::DeliveryNote, $order->number, $shipment->id);

        if (!$document instanceof ShippingDocument) {
            $document = ShippingDocument::deliveryNote($this->register->next(ShippingDocumentKind::DeliveryNote->value), $shipment, $at);
            $this->documents->addDocument($document);
        }

        $gift = $order->giftOptions() ?? GiftOptions::of();
        $lines = [];

        foreach ($order->lines() as $line) {
            $quantity = $shipment->quantityOf($line->reference->value());

            if ($quantity > 0) {
                $lines[] = new DeliveryNoteLine($line->reference->value(), $line->description, $quantity, $gift->pricesHidden ? null : $line->unitPrice);
            }
        }

        return new DeliveryNote($document->number, $order->number, $shipment->id, $document->issuedAt, $lines, $shipment->carrier, $shipment->tracking, $gift->wrapped, $gift->recycledWrap, $gift->message, $gift->pricesHidden);
    }

    public function pickingSlip(Order $order, DateTimeImmutable $at): PickingSlip
    {
        $document = $this->documents->findDocument(ShippingDocumentKind::PickingSlip, $order->number, null);

        if (!$document instanceof ShippingDocument) {
            $document = ShippingDocument::pickingSlip($this->register->next(ShippingDocumentKind::PickingSlip->value), $order->number, $at);
            $this->documents->addDocument($document);
        }

        $held = [];
        $reserved = [];

        foreach ($this->stock->reservationsOf($order->number) as $reservation) {
            $reserved[] = $reservation->sku;

            if (ReservationState::Held === $reservation->state()) {
                $key = $reservation->location."\0".$reservation->sku;
                $held[$key] = new PickingLine($reservation->sku, $reservation->location, ($held[$key]->quantity ?? 0) + $reservation->remaining());
            }
        }

        ksort($held, \SORT_STRING);
        $untracked = [];

        foreach ($order->lines() as $line) {
            $sku = $line->reference->id;

            if ('product' === $line->reference->type && !\in_array($sku, $reserved, true)) {
                $untracked[$sku] = new PickingLine($sku, null, $line->quantity);
            }
        }

        ksort($untracked, \SORT_STRING);
        $gift = $order->giftOptions();

        return new PickingSlip($document->number, $order->number, $document->issuedAt, [...array_values($held), ...array_values($untracked)], true === $gift?->wrapped, true === $gift?->recycledWrap);
    }
}
