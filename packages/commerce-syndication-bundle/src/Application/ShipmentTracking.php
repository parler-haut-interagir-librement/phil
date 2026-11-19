<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Application;

use DateTimeImmutable;
use Phil\CommerceSyndicationBundle\Application\Port\PartnerShipmentHandler;
use Phil\CommerceSyndicationBundle\Domain\Exception\SyndicationRefused;
use Phil\CommerceSyndicationBundle\Domain\Exception\UnknownPartner;
use Phil\CommerceSyndicationBundle\Domain\Model\OutgoingMessage;
use Phil\CommerceSyndicationBundle\Domain\Model\Partner;
use Phil\CommerceSyndicationBundle\Domain\Model\PartnerShipment;
use Phil\CommerceSyndicationBundle\Domain\Partner\PartnerRole;
use Phil\CommerceSyndicationBundle\Domain\Repository\LedgerRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\MessageRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\PartnerRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\PartnerShipmentRepository;

/**
 * The parcels of a forwarded order, from the supplier who ships them to the reseller who sold them: a
 * `shipment.sent` message per parcel, with its SKUs, carrier and tracking number, never an address.
 *
 * **Sending a shipment twice changes nothing**, nor does receiving it twice: a parcel is known by its id at the
 * supplier. Only an order in the ledger with the partner — forwarded by that reseller, or to that supplier — is
 * tracked, and only for the SKUs and the units it holds.
 */
final readonly class ShipmentTracking
{
    public const string TYPE = 'shipment.sent';

    /**
     * @param iterable<PartnerShipmentHandler> $handlers
     */
    public function __construct(
        private PartnerRepository $partners,
        private LedgerRepository $ledger,
        private MessageRepository $messages,
        private PartnerShipmentRepository $shipments,
        private iterable $handlers = [],
    ) {
    }

    /**
     * At the supplier: tells the reseller a parcel of its order left.
     *
     * @param array<int|string, int> $lines SKU → units in the parcel; a numeric SKU is an integer key, as PHP makes it
     *
     * @return bool `false` when the parcel was already sent, and nothing was done
     */
    public function notify(string $reseller, string $orderNumber, string $shipment, array $lines, DateTimeImmutable $at, ?string $carrier = null, ?string $tracking = null): bool
    {
        $partner = $this->partner($reseller, PartnerRole::Reseller);

        if ($this->shipments->findShipment($partner->code, $shipment) instanceof PartnerShipment) {
            return false;
        }

        $sent = PartnerShipment::of($partner, $shipment, $orderNumber, $lines, $at, $carrier, $tracking);
        $this->check($partner, $sent);
        $this->shipments->addShipment($sent);
        $this->messages->queue(OutgoingMessage::to($partner, self::TYPE, [
            'order' => $sent->orderNumber,
            'shipment' => $sent->shipment,
            'carrier' => $sent->carrier,
            'tracking' => $sent->tracking,
            'lines' => self::lines($sent->lines),
        ], $at));

        return true;
    }

    /**
     * At the reseller: records a parcel its supplier sent, and hands it to the `PartnerShipmentHandler` services.
     *
     * @param array<mixed> $data
     */
    public function received(Partner $supplier, array $data, DateTimeImmutable $described): void
    {
        if (PartnerRole::Supplier !== $supplier->role) {
            throw SyndicationRefused::role($supplier->code, PartnerRole::Supplier->value);
        }

        $data = new MessageData($data);
        $shipment = PartnerShipment::of($supplier, $data->string('shipment'), $data->string('order'), $data->quantities(), $described, $data->optionalString('carrier'), $data->optionalString('tracking'));

        if ($this->shipments->findShipment($supplier->code, $shipment->shipment) instanceof PartnerShipment) {
            return;
        }

        $this->check($supplier, $shipment);
        $this->shipments->addShipment($shipment);

        foreach ($this->handlers as $handler) {
            $handler->shipped($shipment);
        }
    }

    /**
     * @return list<PartnerShipment> the parcels of the order with the partner, oldest first
     */
    public function shipmentsOf(string $partner, string $orderNumber): array
    {
        return $this->shipments->shipmentsOf($partner, $orderNumber);
    }

    /**
     * @param array<int|string, int> $lines
     *
     * @return list<array{sku: string, quantity: int}>
     */
    public static function lines(array $lines): array
    {
        $list = [];

        foreach ($lines as $sku => $quantity) {
            $list[] = ['sku' => (string) $sku, 'quantity' => $quantity];
        }

        return $list;
    }

    private function check(Partner $partner, PartnerShipment $shipment): void
    {
        $entry = $this->ledger->findEntry($partner->code, $shipment->orderNumber) ?? throw SyndicationRefused::unknownOrder($partner->code, $shipment->orderNumber);

        foreach ($shipment->lines as $sku => $quantity) {
            if ($quantity > $entry->quantityOf((string) $sku)) {
                throw SyndicationRefused::notInOrder($shipment->orderNumber, (string) $sku);
            }
        }
    }

    private function partner(string $code, PartnerRole $role): Partner
    {
        $partner = $this->partners->findPartner($code);

        if (!$partner instanceof Partner || !$partner->isActive()) {
            throw UnknownPartner::code($code);
        }

        return $role === $partner->role ? $partner : throw SyndicationRefused::role($code, $role->value);
    }
}
