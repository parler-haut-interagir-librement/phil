<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Tests\Unit\Support;

use DateTimeImmutable;
use Override;
use Phil\CommerceSyndicationBundle\Domain\Model\LedgerEntry;
use Phil\CommerceSyndicationBundle\Domain\Model\OutgoingMessage;
use Phil\CommerceSyndicationBundle\Domain\Model\Partner;
use Phil\CommerceSyndicationBundle\Domain\Model\PartnerReturn;
use Phil\CommerceSyndicationBundle\Domain\Model\PartnerShipment;
use Phil\CommerceSyndicationBundle\Domain\Model\PublishedPrice;
use Phil\CommerceSyndicationBundle\Domain\Model\ReceivedMessage;
use Phil\CommerceSyndicationBundle\Domain\Model\SyndicatedProduct;
use Phil\CommerceSyndicationBundle\Domain\Partner\PartnerRole;
use Phil\CommerceSyndicationBundle\Domain\Repository\LedgerRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\MessageRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\PartnerRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\PartnerReturnRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\PartnerShipmentRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\PublishedPriceRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\SyndicatedProductRepository;

/**
 * Every repository of the syndication, in memory.
 */
final class InMemorySyndication implements PartnerRepository, MessageRepository, SyndicatedProductRepository, LedgerRepository, PublishedPriceRepository, PartnerShipmentRepository, PartnerReturnRepository
{
    /** @var array<string, Partner> */
    public array $partners = [];

    /** @var list<OutgoingMessage> */
    public array $outbox = [];

    /** @var array<string, ReceivedMessage> */
    public array $received = [];

    /** @var array<string, SyndicatedProduct> */
    public array $products = [];

    /** @var list<LedgerEntry> */
    public array $entries = [];

    /** @var list<PublishedPrice> */
    public array $prices = [];

    /** @var list<string> the messages a claim refuses, as if another run held them */
    public array $heldElsewhere = [];

    #[Override]
    public function findPartner(string $code): ?Partner
    {
        return $this->partners[$code] ?? null;
    }

    #[Override]
    public function addPartner(Partner $partner): void
    {
        $this->partners[$partner->code] = $partner;
    }

    #[Override]
    public function activeResellers(): array
    {
        return array_values(array_filter($this->partners, static fn (Partner $partner): bool => PartnerRole::Reseller === $partner->role && $partner->isActive()));
    }

    #[Override]
    public function resellersFed(string $sku): array
    {
        return array_values(array_filter($this->activeResellers(), static fn (Partner $reseller): bool => $reseller->feeds($sku)));
    }

    #[Override]
    public function queue(OutgoingMessage $message): void
    {
        $this->outbox[] = $message;
    }

    #[Override]
    public function due(DateTimeImmutable $at, int $limit): array
    {
        return \array_slice(array_values(array_filter($this->outbox, static fn (OutgoingMessage $message): bool => $message->isDue($at))), 0, $limit);
    }

    #[Override]
    public function claim(OutgoingMessage $message, DateTimeImmutable $at, DateTimeImmutable $until): bool
    {
        if ($message->isClaimed($at) || \in_array($message->id, $this->heldElsewhere, true)) {
            return false;
        }

        $message->claim($until);

        return true;
    }

    #[Override]
    public function wasReceived(string $partner, string $id): bool
    {
        return isset($this->received[$partner.' '.$id]);
    }

    #[Override]
    public function markReceived(ReceivedMessage $message): void
    {
        $this->received[$message->partner.' '.$message->id] = $message;
    }

    #[Override]
    public function purgeOutgoing(DateTimeImmutable $before): int
    {
        $kept = array_values(array_filter($this->outbox, static fn (OutgoingMessage $message): bool => !(($message->deliveredAt() ?? $message->givenUpAt() ?? $before) < $before)));
        $purged = \count($this->outbox) - \count($kept);
        $this->outbox = $kept;

        return $purged;
    }

    #[Override]
    public function purgeReceived(DateTimeImmutable $before): int
    {
        $kept = array_filter($this->received, static fn (ReceivedMessage $message): bool => $message->receivedAt >= $before);
        $purged = \count($this->received) - \count($kept);
        $this->received = $kept;

        return $purged;
    }

    #[Override]
    public function recordPrice(PublishedPrice $price): void
    {
        $this->prices[] = $price;
    }

    #[Override]
    public function latestPrice(string $reseller, string $sku, string $currency): ?PublishedPrice
    {
        $prices = $this->pricesOf($reseller, $sku, $currency);

        return [] === $prices ? null : $prices[\count($prices) - 1];
    }

    #[Override]
    public function latestPrices(string $sku, string $currency, array $resellers): array
    {
        $prices = [];

        foreach ($resellers as $reseller) {
            $latest = $this->latestPrice($reseller, $sku, $currency);

            if ($latest instanceof PublishedPrice) {
                $prices[$reseller] = $latest;
            }
        }

        return $prices;
    }

    #[Override]
    public function pricesInEffectSince(string $reseller, array $skus, string $currency, DateTimeImmutable $since): array
    {
        $prices = [];

        foreach ($skus as $sku) {
            $before = array_values(array_filter($this->pricesOf($reseller, $sku, $currency), static fn (PublishedPrice $price): bool => $price->publishedAt < $since));
            $recent = array_values(array_filter($this->pricesOf($reseller, $sku, $currency), static fn (PublishedPrice $price): bool => $price->publishedAt >= $since));
            $inEffect = [...\array_slice($before, -1), ...$recent];

            if ([] !== $inEffect) {
                $prices[$sku] = $inEffect;
            }
        }

        return $prices;
    }

    /**
     * @return list<PublishedPrice> oldest first
     */
    private function pricesOf(string $reseller, string $sku, string $currency): array
    {
        return array_values(array_filter($this->prices, static fn (PublishedPrice $price): bool => $price->reseller === $reseller && $price->sku === $sku && $price->cost->currency->code === $currency));
    }

    #[Override]
    public function findProduct(string $supplier, string $sku): ?SyndicatedProduct
    {
        return $this->products[$supplier.':'.$sku] ?? null;
    }

    #[Override]
    public function addProduct(SyndicatedProduct $product): void
    {
        $this->products[$product->supplier.':'.$product->sku] = $product;
    }

    #[Override]
    public function findEntry(string $partner, string $orderNumber): ?LedgerEntry
    {
        return array_find($this->entries, static fn (LedgerEntry $entry): bool => $entry->partner === $partner && $entry->orderNumber === $orderNumber && null === $entry->returnNumber);
    }

    #[Override]
    public function entriesOf(string $partner, string $orderNumber): array
    {
        return array_values(array_filter($this->entries, static fn (LedgerEntry $entry): bool => $entry->partner === $partner && $entry->orderNumber === $orderNumber));
    }

    /** @var array<string, PartnerShipment> */
    public array $shipments = [];

    /** @var array<string, PartnerReturn> */
    public array $returns = [];

    #[Override]
    public function findShipment(string $partner, string $shipment): ?PartnerShipment
    {
        return $this->shipments[$partner.'@'.$shipment] ?? null;
    }

    #[Override]
    public function addShipment(PartnerShipment $shipment): void
    {
        $this->shipments[$shipment->partner.'@'.$shipment->shipment] = $shipment;
    }

    #[Override]
    public function shipmentsOf(string $partner, string $orderNumber): array
    {
        return array_values(array_filter($this->shipments, static fn (PartnerShipment $shipment): bool => $shipment->partner === $partner && $shipment->orderNumber === $orderNumber));
    }

    #[Override]
    public function findReturn(string $partner, string $returnNumber): ?PartnerReturn
    {
        return $this->returns[$partner.'@'.$returnNumber] ?? null;
    }

    #[Override]
    public function addReturn(PartnerReturn $return): void
    {
        $this->returns[$return->partner.'@'.$return->returnNumber] = $return;
    }

    #[Override]
    public function returnsOf(string $partner, string $orderNumber): array
    {
        return array_values(array_filter($this->returns, static fn (PartnerReturn $return): bool => $return->partner === $partner && $return->orderNumber === $orderNumber));
    }

    #[Override]
    public function record(LedgerEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    #[Override]
    public function openEntries(string $partner): array
    {
        return array_values(array_filter($this->entries, static fn (LedgerEntry $entry): bool => $entry->partner === $partner && !$entry->settledAt() instanceof DateTimeImmutable));
    }
}
