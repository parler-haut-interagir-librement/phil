<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Application;

use DateTimeImmutable;
use Phil\CommerceSyndicationBundle\Application\Port\PartnerReturnHandler;
use Phil\CommerceSyndicationBundle\Domain\Exception\SyndicationRefused;
use Phil\CommerceSyndicationBundle\Domain\Exception\UnknownPartner;
use Phil\CommerceSyndicationBundle\Domain\Model\LedgerEntry;
use Phil\CommerceSyndicationBundle\Domain\Model\OutgoingMessage;
use Phil\CommerceSyndicationBundle\Domain\Model\Partner;
use Phil\CommerceSyndicationBundle\Domain\Model\PartnerReturn;
use Phil\CommerceSyndicationBundle\Domain\Partner\PartnerRole;
use Phil\CommerceSyndicationBundle\Domain\Repository\LedgerRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\MessageRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\PartnerRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\PartnerReturnRepository;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * Goods a reseller sends back to the supplier of an order it forwarded, and their credit in the ledger:
 *
 * 1. the reseller requests the return — `return.requested`, with the SKUs, the units and the tracking number of the
 *    parcel —, never more of a SKU than the order holds less what earlier returns took;
 * 2. the supplier receives the goods, credits what it accepted at the unit costs of its own ledger, and answers
 *    `return.received`;
 * 3. the reseller records the same credit, computed from its own ledger, and refuses a credit that differs.
 *
 * Both sides keep the credit as an entry of its own under the return's number, of the sign opposite to the order's.
 * The messages carry SKUs, quantities and amounts only, never the buyer.
 */
final readonly class PartnerReturns
{
    public const string REQUESTED = 'return.requested';

    public const string RECEIVED = 'return.received';

    /**
     * @param iterable<PartnerReturnHandler> $handlers
     */
    public function __construct(
        private PartnerRepository $partners,
        private LedgerRepository $ledger,
        private MessageRepository $messages,
        private PartnerReturnRepository $returns,
        private iterable $handlers = [],
    ) {
    }

    /**
     * At the reseller: announces goods sent back to the supplier of a forwarded order.
     *
     * @param array<int|string, int> $lines SKU → units sent back; a numeric SKU is an integer key, as PHP makes it
     */
    public function request(string $supplier, string $orderNumber, string $returnNumber, array $lines, DateTimeImmutable $at, ?string $carrier = null, ?string $tracking = null): PartnerReturn
    {
        $partner = $this->partner($supplier, PartnerRole::Supplier);

        if ($this->returns->findReturn($partner->code, $returnNumber) instanceof PartnerReturn) {
            throw SyndicationRefused::returnKnown($returnNumber);
        }

        $entry = $this->entry($partner, $orderNumber);
        $return = PartnerReturn::request($partner, $returnNumber, $orderNumber, $entry->amount->currency, $lines, $at, $carrier, $tracking);
        $this->checkLeft($partner, $entry, $return);
        $this->returns->addReturn($return);
        $this->messages->queue(OutgoingMessage::to($partner, self::REQUESTED, [
            'order' => $orderNumber,
            'return' => $returnNumber,
            'currency' => $return->currency()->code,
            'carrier' => $carrier,
            'tracking' => $tracking,
            'lines' => ShipmentTracking::lines($return->requested),
        ], $at));

        return $return;
    }

    /**
     * At the supplier: receives the goods of a return, credits the units accepted — every unit announced by default —
     * at the unit costs of the order's entry, and tells the reseller.
     *
     * @param array<int|string, int>|null $lines SKU → units accepted
     */
    public function receive(string $reseller, string $returnNumber, ?array $lines, DateTimeImmutable $at): PartnerReturn
    {
        $partner = $this->partner($reseller, PartnerRole::Reseller);
        $return = $this->returns->findReturn($partner->code, $returnNumber) ?? throw SyndicationRefused::unknownReturn($partner->code, $returnNumber);
        $lines ??= $return->requested;
        $credit = $this->credit($partner, $return, $lines, $at, negative: true);
        $this->messages->queue(OutgoingMessage::to($partner, self::RECEIVED, [
            'order' => $return->orderNumber,
            'return' => $returnNumber,
            'currency' => $credit->currency->code,
            'credit' => $credit->minorUnits,
            'lines' => ShipmentTracking::lines($lines),
        ], $at));

        return $return;
    }

    /**
     * At the supplier, from the inbox: a reseller announced a return.
     *
     * @param array<mixed> $data
     */
    public function requested(Partner $reseller, array $data, DateTimeImmutable $at): void
    {
        $this->expect($reseller, PartnerRole::Reseller);
        $data = new MessageData($data);
        $entry = $this->entry($reseller, $data->string('order'));
        $return = PartnerReturn::request($reseller, $data->string('return'), $entry->orderNumber, Currency::of($data->string('currency')), $data->quantities(), $at, $data->optionalString('carrier'), $data->optionalString('tracking'));

        if ($this->returns->findReturn($reseller->code, $return->returnNumber) instanceof PartnerReturn) {
            return;
        }

        if (!$return->currency()->equals($entry->amount->currency)) {
            throw SyndicationRefused::unreadable(self::REQUESTED, "its currency is the order's");
        }

        $this->checkLeft($reseller, $entry, $return);
        $this->returns->addReturn($return);

        foreach ($this->handlers as $handler) {
            $handler->requested($return);
        }
    }

    /**
     * At the reseller, from the inbox: the supplier received a return and credited it.
     *
     * @param array<mixed> $data
     */
    public function received(Partner $supplier, array $data, DateTimeImmutable $at): void
    {
        $this->expect($supplier, PartnerRole::Supplier);
        $data = new MessageData($data);
        $returnNumber = $data->string('return');
        $return = $this->returns->findReturn($supplier->code, $returnNumber) ?? throw SyndicationRefused::unknownReturn($supplier->code, $returnNumber);
        $claimed = Money::of($data->int('credit'), Currency::of($data->string('currency')));
        $lines = $data->quantities();
        $computed = $this->computedCredit($this->entry($supplier, $return->orderNumber), $lines, $return->currency());

        if (!$claimed->equals($computed)) {
            throw SyndicationRefused::creditMismatch($returnNumber, $claimed->toDecimal(), $computed->toDecimal());
        }

        $this->credit($supplier, $return, $lines, $at, negative: false);

        foreach ($this->handlers as $handler) {
            $handler->received($return);
        }
    }

    /**
     * @return list<PartnerReturn> the returns of the order with the partner, oldest first
     */
    public function returnsOf(string $partner, string $orderNumber): array
    {
        return $this->returns->returnsOf($partner, $orderNumber);
    }

    /**
     * Records the return received and its credit in the ledger, of the sign opposite to the order's entry.
     *
     * @param array<int|string, int> $lines
     */
    private function credit(Partner $partner, PartnerReturn $return, array $lines, DateTimeImmutable $at, bool $negative): Money
    {
        $entry = $this->entry($partner, $return->orderNumber);
        $credit = $this->computedCredit($entry, $lines, $return->currency());
        $return->receive($lines, $credit, $at);
        $credited = [];

        foreach ($lines as $sku => $quantity) {
            $credited[] = ['sku' => (string) $sku, 'quantity' => $quantity, 'unit_cost' => $this->unitCost($entry, $sku)];
        }

        $this->ledger->record(LedgerEntry::credit($partner, $return->orderNumber, $return->returnNumber, $negative ? $credit->negate() : $credit, $at, $credited));

        return $credit;
    }

    /**
     * @param array<int|string, int> $lines
     */
    private function computedCredit(LedgerEntry $entry, array $lines, Currency $currency): Money
    {
        $credit = Money::zero($currency);

        foreach ($lines as $sku => $quantity) {
            $credit = $credit->add(Money::of($this->unitCost($entry, $sku), $currency)->multiply($quantity));
        }

        return $credit;
    }

    private function unitCost(LedgerEntry $entry, int|string $sku): int
    {
        return $entry->unitCostOf((string) $sku) ?? throw SyndicationRefused::notInOrder($entry->orderNumber, (string) $sku);
    }

    /**
     * Refuses more units of a SKU than the order holds less what its earlier returns announced.
     */
    private function checkLeft(Partner $partner, LedgerEntry $entry, PartnerReturn $return): void
    {
        $taken = [];

        foreach ($this->returns->returnsOf($partner->code, $entry->orderNumber) as $earlier) {
            foreach ($earlier->requested as $sku => $quantity) {
                $taken[$sku] = ($taken[$sku] ?? 0) + $quantity;
            }
        }

        foreach ($return->requested as $sku => $quantity) {
            $left = $entry->quantityOf((string) $sku) - ($taken[$sku] ?? 0);

            if ($quantity > $left) {
                throw SyndicationRefused::overReturned($entry->orderNumber, (string) $sku, $quantity, $left);
            }
        }
    }

    private function entry(Partner $partner, string $orderNumber): LedgerEntry
    {
        return $this->ledger->findEntry($partner->code, $orderNumber) ?? throw SyndicationRefused::unknownOrder($partner->code, $orderNumber);
    }

    private function partner(string $code, PartnerRole $role): Partner
    {
        $partner = $this->partners->findPartner($code);

        if (!$partner instanceof Partner || !$partner->isActive()) {
            throw UnknownPartner::code($code);
        }

        $this->expect($partner, $role);

        return $partner;
    }

    private function expect(Partner $partner, PartnerRole $role): void
    {
        if ($role !== $partner->role) {
            throw SyndicationRefused::role($partner->code, $role->value);
        }
    }
}
