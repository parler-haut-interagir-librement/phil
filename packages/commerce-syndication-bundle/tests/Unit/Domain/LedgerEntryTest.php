<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\CommerceSyndicationBundle\Domain\Exception\SyndicationRefused;
use Phil\CommerceSyndicationBundle\Domain\Exception\UnknownPartner;
use Phil\CommerceSyndicationBundle\Domain\Model\LedgerEntry;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\Partners;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LedgerEntry::class)]
#[CoversClass(SyndicationRefused::class)]
#[CoversClass(UnknownPartner::class)]
final class LedgerEntryTest extends TestCase
{
    public function testAnEntryKeepsWhatIsOwedForAnOrder(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $entry = LedgerEntry::of(Partners::supplier(), 'A-0001', Money::of(-1_400, Currency::of('CAD')), $at);

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $entry->id);
        self::assertSame(['supplier', 'A-0001', -1_400, 'CAD', $at, null], [$entry->partner, $entry->orderNumber, $entry->amount->minorUnits, $entry->amount->currency->code, $entry->recordedAt, $entry->settledAt()]);
    }

    public function testTheUnitsOfASkuAreAddedUpOverTheLines(): void
    {
        $entry = LedgerEntry::of(Partners::supplier(), 'A-0001', Money::of(-1_400, Currency::of('CAD')), new DateTimeImmutable('2026-11-03'), [
            ['sku' => 'TEA-1', 'quantity' => 2, 'unit_cost' => 700],
            ['sku' => 'MUG', 'quantity' => 1, 'unit_cost' => 1_100],
            ['sku' => 'TEA-1', 'quantity' => 3, 'unit_cost' => 700],
        ]);

        self::assertSame([5, 1, 0, 700, null], [$entry->quantityOf('TEA-1'), $entry->quantityOf('MUG'), $entry->quantityOf('MAP'), $entry->unitCostOf('TEA-1'), $entry->unitCostOf('MAP')]);
    }

    public function testAnEntryIsSettledOnce(): void
    {
        $entry = LedgerEntry::of(Partners::supplier(), 'A-0001', Money::of(100, Currency::of('CAD')), new DateTimeImmutable());
        $first = new DateTimeImmutable('2026-11-30');

        $entry->settle($first);
        $entry->settle(new DateTimeImmutable('2026-12-31'));

        self::assertSame($first, $entry->settledAt());
    }

    public function testTheRefusalsSayWhy(): void
    {
        self::assertSame([
            'No active partner is known as "shop".',
            'The partner shop is not a supplier.',
            'The "order.forwarded" message cannot be read: "sku" is a string.',
            'The supplier shop feeds no product TEA.',
            'The reseller shop is not fed the product TEA.',
            'The order A-1 is not placed: only a placed order is forwarded.',
        ], [
            UnknownPartner::code('shop')->getMessage(),
            SyndicationRefused::role('shop', 'supplier')->getMessage(),
            SyndicationRefused::unreadable('order.forwarded', '"sku" is a string')->getMessage(),
            SyndicationRefused::unknownProduct('shop', 'TEA')->getMessage(),
            SyndicationRefused::notFed('shop', 'TEA')->getMessage(),
            SyndicationRefused::notPlaced('A-1')->getMessage(),
        ]);
        self::assertInstanceOf(DomainError::class, UnknownPartner::code('shop'));
        self::assertInstanceOf(DomainError::class, SyndicationRefused::notPlaced('A-1'));
    }
}
