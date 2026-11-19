<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Application;

use DateTimeImmutable;
use Phil\CommerceSyndicationBundle\Domain\Model\OutgoingMessage;
use Phil\CommerceSyndicationBundle\Domain\Model\Partner;
use Phil\CommerceSyndicationBundle\Domain\Model\PublishedPrice;
use Phil\CommerceSyndicationBundle\Domain\Repository\MessageRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\PartnerRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\PublishedPriceRepository;
use Phil\CommerceSyndicationBundle\Domain\ValueObject\FeedItem;
use Phil\Money\Money;

/**
 * Feeds the resellers: each change of a product sold here — its price, its stock, its withdrawal — queues one
 * `product.updated` message for each active reseller fed that SKU, with the retail price and what a unit costs that
 * reseller: the retail price less its share.
 *
 * Each price published is kept, once per change: the supplier checks the cost of a forwarded order's lines against
 * what it published to that reseller.
 */
final readonly class Publisher
{
    public const string TYPE = 'product.updated';

    private const int PARTS = 1_000_000;

    public function __construct(
        private PartnerRepository $partners,
        private MessageRepository $messages,
        private PublishedPriceRepository $prices,
    ) {
    }

    /**
     * @return int how many messages were queued
     */
    public function publish(string $sku, string $name, Money $retail, string $taxCategory, int $available, bool $active, DateTimeImmutable $at): int
    {
        $queued = 0;
        // The resellers fed the SKU, and the last price each was sent: two reads, whatever the number of resellers.
        $resellers = $this->partners->resellersFed($sku);
        $latest = $this->prices->latestPrices($sku, $retail->currency->code, array_map(static fn (Partner $reseller): string => $reseller->code, $resellers));

        foreach ($resellers as $reseller) {
            $kept = intdiv($retail->minorUnits * $reseller->share() + self::PARTS / 2, self::PARTS);
            $item = FeedItem::of($sku, $name, $retail, $retail->subtract(Money::of($kept, $retail->currency)), $taxCategory, $available, $active);
            $this->messages->queue(OutgoingMessage::to($reseller, self::TYPE, $this->data($item), $at));
            $this->keep($reseller, $item, $at, $latest[$reseller->code] ?? null);
            ++$queued;
        }

        return $queued;
    }

    private function keep(Partner $reseller, FeedItem $item, DateTimeImmutable $at, ?PublishedPrice $latest): void
    {
        if (!$latest instanceof PublishedPrice || !$latest->samePrice($item->retail, $item->cost)) {
            $this->prices->recordPrice(PublishedPrice::of($reseller, $item->sku, $item->retail, $item->cost, $at));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function data(FeedItem $item): array
    {
        return [
            'sku' => $item->sku,
            'name' => $item->name,
            'currency' => $item->retail->currency->code,
            'retail' => $item->retail->minorUnits,
            'cost' => $item->cost->minorUnits,
            'tax_category' => $item->taxCategory,
            'available' => $item->available,
            'active' => $item->active,
        ];
    }
}
