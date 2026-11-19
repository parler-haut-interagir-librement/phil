<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Application;

use DateTimeImmutable;
use Phil\CommerceSyndicationBundle\Application\Port\ForwardedOrderHandler;
use Phil\CommerceSyndicationBundle\Domain\Exception\SyndicationRefused;
use Phil\CommerceSyndicationBundle\Domain\Exception\UnknownPartner;
use Phil\CommerceSyndicationBundle\Domain\Model\LedgerEntry;
use Phil\CommerceSyndicationBundle\Domain\Model\OutgoingMessage;
use Phil\CommerceSyndicationBundle\Domain\Model\Partner;
use Phil\CommerceSyndicationBundle\Domain\Model\PublishedPrice;
use Phil\CommerceSyndicationBundle\Domain\Model\ReceivedMessage;
use Phil\CommerceSyndicationBundle\Domain\Model\SyndicatedProduct;
use Phil\CommerceSyndicationBundle\Domain\Partner\PartnerRole;
use Phil\CommerceSyndicationBundle\Domain\Repository\LedgerRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\MessageRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\PartnerRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\PublishedPriceRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\SyndicatedProductRepository;
use Phil\CommerceSyndicationBundle\Domain\ValueObject\FeedItem;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Exception\MoneyException;
use Phil\Money\Money;
use Phil\StandardWebhooks\Exception\WebhookException;
use Phil\StandardWebhooks\Tolerance;
use Phil\StandardWebhooks\Verifier;
use Phil\StandardWebhooks\WebhookHeaders;
use Phil\StandardWebhooks\WebhookReceiver;

/**
 * Receives a partner's messages: verified with that partner's key, addressed to this application, applied once.
 *
 * - `product.updated`, from a supplier, describes one of its products anew;
 * - `order.forwarded`, from a reseller, records what it owes for the order and hands the order to the
 *   `ForwardedOrderHandler` services, which ship it. **The supplier does not take the reseller's word for the cost**:
 *   each line's `unit_cost` must be a cost this supplier published to that reseller for that SKU, in effect at some
 *   moment of the grace window; the ledger records the supplier's own figure;
 * - `shipment.sent`, from a supplier, records a parcel of a forwarded order, through `ShipmentTracking`;
 * - `return.requested`, from a reseller, and `return.received`, from a supplier, move a return and its credit,
 *   through `PartnerReturns`.
 *
 * A type it does not know is acknowledged and ignored: a partner on a newer version may send more.
 */
final readonly class Inbox
{
    private Tolerance $tolerance;

    /**
     * @param string                          $identity      this application's name at its partners, which their messages are addressed to
     * @param int                             $tolerance     how many seconds a message's timestamp may stray from now
     * @param int                             $costGraceDays how many days a published cost still pays a forwarded line after the next one replaced it
     * @param iterable<ForwardedOrderHandler> $handlers
     */
    public function __construct(
        private PartnerRepository $partners,
        private SyndicatedProductRepository $products,
        private MessageRepository $messages,
        private LedgerRepository $ledger,
        private PublishedPriceRepository $prices,
        private string $identity,
        int $tolerance,
        private int $costGraceDays = 7,
        private iterable $handlers = [],
        private Verifier $verifier = new Verifier(),
        private ?ShipmentTracking $tracking = null,
        private ?PartnerReturns $returns = null,
    ) {
        $this->tolerance = Tolerance::ofSeconds($tolerance);

        if ($costGraceDays < 0) {
            throw InvalidValue::because(\sprintf('A grace window is zero days or more; %d is refused.', $costGraceDays));
        }
    }

    /**
     * @return bool `false` when the message was already received, and nothing was done
     *
     * @throws UnknownPartner     when no active partner is known by that code
     * @throws SyndicationRefused when the message is not one the partner may send, cannot be read, or claims a cost
     *                            that was never published
     * @throws WebhookException   when the message is not signed by the partner, too old, or addressed elsewhere
     */
    public function receive(string $partnerCode, WebhookHeaders $headers, string $rawBody, DateTimeImmutable $at): bool
    {
        $partner = $this->partners->findPartner($partnerCode);

        if (!$partner instanceof Partner || !$partner->isActive()) {
            throw UnknownPartner::code($partnerCode);
        }

        $verified = new WebhookReceiver(new PartnerKey($partner), $this->tolerance, $this->identity, $this->verifier)->receive($headers, $rawBody, $at);

        if ($this->messages->wasReceived($partner->code, $verified->id->value)) {
            return false;
        }

        $envelope = $verified->envelope();

        try {
            match ($envelope->type) {
                Publisher::TYPE => $this->productUpdated($partner, $envelope->data, $this->moment($envelope->timestamp, $envelope->type)),
                Forwarder::TYPE => $this->orderForwarded($partner, $envelope->data, $at),
                ShipmentTracking::TYPE => $this->tracking?->received($partner, $envelope->data, $this->moment($envelope->timestamp, $envelope->type)),
                PartnerReturns::REQUESTED => $this->returns?->requested($partner, $envelope->data, $at),
                PartnerReturns::RECEIVED => $this->returns?->received($partner, $envelope->data, $at),
                default => null,
            };
        } catch (InvalidValue|MoneyException $invalid) {
            // A Money exception is an amount out of range or a currency unknown: the message, not this side, is wrong.
            throw SyndicationRefused::unreadable($envelope->type, $invalid->getMessage());
        }

        $this->messages->markReceived(ReceivedMessage::of($verified->id->value, $partner, $envelope->type, $at));

        return true;
    }

    /**
     * @param array<mixed> $data
     */
    private function productUpdated(Partner $supplier, array $data, DateTimeImmutable $described): void
    {
        $this->expect($supplier, PartnerRole::Supplier);
        $currency = Currency::of($this->string($data, 'currency'));
        $item = FeedItem::of(
            $this->string($data, 'sku'),
            $this->string($data, 'name'),
            Money::of($this->int($data, 'retail'), $currency),
            Money::of($this->int($data, 'cost'), $currency),
            $this->string($data, 'tax_category'),
            $this->int($data, 'available'),
            $this->bool($data, 'active'),
        );

        $product = $this->products->findProduct($supplier->code, $item->sku);

        if (!$product instanceof SyndicatedProduct) {
            $this->products->addProduct(SyndicatedProduct::from($supplier, $item, $described));

            return;
        }

        $product->describe($item, $described);
    }

    /**
     * @param array<mixed> $data
     */
    private function orderForwarded(Partner $reseller, array $data, DateTimeImmutable $at): void
    {
        $this->expect($reseller, PartnerRole::Reseller);
        $number = $this->string($data, 'order');

        if (1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $number)) {
            throw SyndicationRefused::unreadable(Forwarder::TYPE, 'its order number is 1 to 64 visible ASCII characters');
        }

        $currency = Currency::of($this->string($data, 'currency'));
        $rawLines = $data['lines'] ?? null;
        $shipTo = $data['ship_to'] ?? [];

        if (!\is_array($rawLines) || [] === $rawLines || !array_is_list($rawLines) || !\is_array($shipTo)) {
            throw SyndicationRefused::unreadable(Forwarder::TYPE, 'it carries a list of lines and an object "ship_to"');
        }

        $claims = [];

        foreach ($rawLines as $rawLine) {
            if (!\is_array($rawLine)) {
                throw SyndicationRefused::unreadable(Forwarder::TYPE, 'each line is an object');
            }

            $sku = $this->string($rawLine, 'sku');
            $quantity = $this->int($rawLine, 'quantity');
            $unitCost = Money::of($this->int($rawLine, 'unit_cost'), $currency);

            if (!$reseller->feeds($sku)) {
                throw SyndicationRefused::notFed($reseller->code, $sku);
            }

            if ($quantity < 1 || $unitCost->isNegative()) {
                throw SyndicationRefused::unreadable(Forwarder::TYPE, 'a line holds one unit or more, at a cost of zero or more');
            }

            $claims[] = ['sku' => $sku, 'quantity' => $quantity, 'unitCost' => $unitCost];
        }

        // Every line read and checked, the prices of all their SKUs in one read rather than one per line.
        $since = $at->modify(\sprintf('-%d days', $this->costGraceDays));
        $published = $this->prices->pricesInEffectSince($reseller->code, array_column($claims, 'sku'), $currency->code, $since);
        $owed = Money::zero($currency);
        $lines = [];

        foreach ($claims as $claim) {
            $unitCost = $this->publishedCost($reseller, $claim['sku'], $claim['unitCost'], $published[$claim['sku']] ?? []);
            $owed = $owed->add($unitCost->multiply($claim['quantity']));
            $lines[] = ['sku' => $claim['sku'], 'quantity' => $claim['quantity'], 'unitCost' => $unitCost];
        }

        if ($this->ledger->findEntry($reseller->code, $number) instanceof LedgerEntry) {
            return;
        }

        $this->ledger->record(LedgerEntry::of($reseller, $number, $owed, $at, array_map(
            static fn (array $line): array => ['sku' => $line['sku'], 'quantity' => $line['quantity'], 'unit_cost' => $line['unitCost']->minorUnits],
            $lines,
        )));
        $order = new ForwardedOrder($reseller->code, $number, $lines, $shipTo, $owed);

        foreach ($this->handlers as $handler) {
            $handler->handle($order);
        }
    }

    /**
     * The cost this supplier published to the reseller that the line claims, in effect at some moment of the grace
     * window — the price of now, or one the reseller may still have priced its order at.
     *
     * @param list<PublishedPrice> $published the prices of the SKU in effect during the window
     */
    private function publishedCost(Partner $reseller, string $sku, Money $claimed, array $published): Money
    {
        foreach ($published as $price) {
            if ($price->cost->equals($claimed)) {
                return $price->cost;
            }
        }

        throw SyndicationRefused::costNotPublished($reseller->code, $sku, $claimed->toDecimal(), $this->costGraceDays);
    }

    private function expect(Partner $partner, PartnerRole $role): void
    {
        if ($role !== $partner->role) {
            throw SyndicationRefused::role($partner->code, $role->value);
        }
    }

    private function moment(?string $timestamp, string $type): DateTimeImmutable
    {
        $moment = null === $timestamp ? false : DateTimeImmutable::createFromFormat(OutgoingMessage::MOMENT, $timestamp);

        return false === $moment ? throw SyndicationRefused::unreadable($type, 'its envelope carries the moment of the change') : $moment;
    }

    /**
     * @param array<mixed> $data
     */
    private function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return \is_string($value) ? $value : throw InvalidValue::because(\sprintf('"%s" is a string', $key));
    }

    /**
     * @param array<mixed> $data
     */
    private function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return \is_int($value) ? $value : throw InvalidValue::because(\sprintf('"%s" is an integer', $key));
    }

    /**
     * @param array<mixed> $data
     */
    private function bool(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;

        return \is_bool($value) ? $value : throw InvalidValue::because(\sprintf('"%s" is a boolean', $key));
    }
}
