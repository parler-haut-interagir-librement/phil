<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceSyndicationBundle\Domain\Exception\SyndicationRefused;
use Phil\CommerceSyndicationBundle\Domain\Exception\UnknownPartner;
use Phil\CommerceSyndicationBundle\Domain\Model\LedgerEntry;
use Phil\CommerceSyndicationBundle\Domain\Model\OutgoingMessage;
use Phil\CommerceSyndicationBundle\Domain\Model\Partner;
use Phil\CommerceSyndicationBundle\Domain\Partner\PartnerRole;
use Phil\CommerceSyndicationBundle\Domain\Repository\LedgerRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\MessageRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\PartnerRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\SyndicatedProductRepository;
use Phil\Money\Money;

/**
 * Sends the syndicated lines of a placed order to their suppliers, who ship them: one `order.forwarded` message per
 * supplier, and in the ledger what is owed to it — each unit at the cost its feed last gave.
 *
 * **Forwarding twice changes nothing**: an order already in the ledger with a supplier is not sent to it again.
 */
final readonly class Forwarder
{
    public const string TYPE = 'order.forwarded';

    public function __construct(
        private PartnerRepository $partners,
        private SyndicatedProductRepository $products,
        private MessageRepository $messages,
        private LedgerRepository $ledger,
    ) {
    }

    /**
     * @param array<string, mixed> $shipTo where the supplier ships, in whatever shape the partners agreed on
     *
     * @return list<string> the suppliers the order was sent to now
     */
    public function forward(Order $order, array $shipTo, DateTimeImmutable $at): array
    {
        if (OrderState::Placed !== $order->state()) {
            throw SyndicationRefused::notPlaced($order->number);
        }

        $groups = [];

        foreach ($order->lines() as $line) {
            if (SyndicatedSellables::TYPE === $line->reference->type) {
                [$supplier, $sku] = SyndicatedSellables::split($line->reference->id);
                $groups[$supplier][] = [$supplier, $sku, $line->quantity];
            }
        }

        $forwarded = [];

        foreach ($groups as $group) {
            $supplier = $this->supplier($group[0][0]);

            if ($this->ledger->findEntry($supplier->code, $order->number) instanceof LedgerEntry) {
                continue;
            }

            $owed = Money::zero($order->currency);
            $lines = [];

            foreach ($group as [, $sku, $quantity]) {
                $product = $this->products->findProduct($supplier->code, $sku) ?? throw SyndicationRefused::unknownProduct($supplier->code, $sku);
                $owed = $owed->add($product->cost->multiply($quantity));
                $lines[] = ['sku' => $sku, 'quantity' => $quantity, 'unit_cost' => $product->cost->minorUnits];
            }

            $this->messages->queue(OutgoingMessage::to($supplier, self::TYPE, [
                'order' => $order->number,
                'currency' => $order->currency->code,
                'lines' => $lines,
                'ship_to' => (object) $shipTo,
            ], $at));
            $this->ledger->record(LedgerEntry::of($supplier, $order->number, $owed->negate(), $at, $lines));
            $forwarded[] = $supplier->code;
        }

        return $forwarded;
    }

    private function supplier(string $code): Partner
    {
        $supplier = $this->partners->findPartner($code);

        if (!$supplier instanceof Partner || !$supplier->isActive()) {
            throw UnknownPartner::code($code);
        }

        if (PartnerRole::Supplier !== $supplier->role) {
            throw SyndicationRefused::role($code, 'supplier');
        }

        return $supplier;
    }
}
