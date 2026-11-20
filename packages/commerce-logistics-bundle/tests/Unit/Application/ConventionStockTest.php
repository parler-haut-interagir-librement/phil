<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceLogisticsBundle\Application\ConventionStock;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\Convention;
use Phil\CommerceLogisticsBundle\Domain\Model\ConventionQuantity;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\PrintJob;
use Phil\CommerceLogisticsBundle\Domain\Model\Reservation;
use Phil\CommerceLogisticsBundle\Domain\Model\StockLevel;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\InMemoryLogistics;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\RecordingPrinter;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConventionStock::class)]
#[CoversClass(Stock::class)]
#[CoversClass(Convention::class)]
#[CoversClass(ConventionQuantity::class)]
#[CoversClass(StockLevel::class)]
#[CoversClass(PrintJob::class)]
final class ConventionStockTest extends TestCase
{
    private InMemoryLogistics $repository;

    private RecordingPrinter $printer;

    private ConventionStock $conventions;

    private Stock $stock;

    protected function setUp(): void
    {
        $this->repository = new InMemoryLogistics();
        $this->repository->addLocation(Location::of('warehouse', 'Warehouse', 10));
        $this->repository->addLocation(Location::of('booth', 'Booth'));

        $this->printer = new RecordingPrinter();
        $this->conventions = new ConventionStock($this->repository, $this->repository, $this->repository, 30, ['web'], [$this->printer]);
        $this->stock = new Stock($this->repository, $this->repository, null, $this->conventions);

        $this->conventions->schedule('fall-con', 'Fall Con', new DateTimeImmutable('2026-12-20'), new DateTimeImmutable('2026-12-21'));
        $this->conventions->enter('fall-con', 'SHIRT-M', 20);
        $this->conventions->enter('fall-con', 'SHIRT-L', 10);

        $this->stock->receive('SHIRT-M', 'booth', 6, new DateTimeImmutable('2026-11-01'));
        $this->stock->receive('DICE', 'warehouse', 5, new DateTimeImmutable('2026-11-01'));
    }

    public function testQuantitiesAreEnteredPerSkuAndConventionAndTheShortfallIsWhatIsNotOnTheShelf(): void
    {
        $this->conventions->enter('fall-con', 'SHIRT-L', 12);

        self::assertSame(['SHIRT-L' => 12, 'SHIRT-M' => 20], $this->conventions->planned('fall-con'));
        self::assertSame(['SHIRT-L' => 12, 'SHIRT-M' => 14], $this->conventions->shortfall('fall-con'));

        $this->stock->receive('SHIRT-M', 'warehouse', 30, new DateTimeImmutable('2026-11-02'));
        self::assertSame(['SHIRT-L' => 12], $this->conventions->shortfall('fall-con'));
    }

    public function testAQuantityIsEnteredForAKnownConventionOnly(): void
    {
        $this->expectException(StockRefused::class);
        $this->expectExceptionMessageIsOrContains('No convention is known as "spring-con".');

        $this->conventions->enter('spring-con', 'SHIRT-M', 1);
    }

    public function testSchedulingAKnownConventionMovesItsDays(): void
    {
        $convention = $this->conventions->schedule('fall-con', 'Ignored', new DateTimeImmutable('2027-01-05 18:30'), new DateTimeImmutable('2027-01-06'));

        self::assertSame(['Fall Con', '2027-01-05 00:00'], [$convention->name, $convention->startsOn()->format('Y-m-d H:i')]);
        self::assertCount(1, $this->repository->conventions);
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function nearness(): iterable
    {
        yield 'thirty days before its first day' => ['2026-11-20T09:00:00Z', ['fall-con']];
        yield 'on its last day' => ['2026-12-21T23:00:00Z', ['fall-con']];
        yield 'thirty-one days before' => ['2026-11-19T09:00:00Z', []];
        yield 'the day after it' => ['2026-12-22T00:00:00Z', []];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('nearness')]
    public function testAConventionIsNearWithinTheWindow(string $at, array $expected): void
    {
        self::assertSame($expected, array_map(static fn (Convention $convention): string => $convention->code, $this->conventions->near(new DateTimeImmutable($at))));
    }

    public function testWhileAConventionIsNearAnOnlineOrderSendsItsConventionItemsToPrint(): void
    {
        $at = new DateTimeImmutable('2026-12-01T10:00:00Z');
        $order = $this->order(['SHIRT-M' => 2, 'DICE' => 1], 'web', $at);

        $this->stock->reserve($order, $at);

        self::assertSame([['2026-0001', 'SHIRT-M', 2]], $this->printer->printed);
        self::assertSame([['DICE', 1]], array_map(static fn (Reservation $reservation): array => [$reservation->sku, $reservation->quantity], $this->repository->reservations));
        self::assertSame(6, $this->stock->available('SHIRT-M'));
        self::assertTrue($this->conventions->routed('2026-0001'));
    }

    public function testWithNoConventionNearAnOnlineOrderTakesFromStockWhenEnoughIsAvailable(): void
    {
        $at = new DateTimeImmutable('2026-11-01T10:00:00Z');
        $this->stock->reserve($this->order(['SHIRT-M' => 6, 'SHIRT-L' => 1], 'web', $at), $at);

        self::assertSame([['2026-0001', 'SHIRT-L', 1]], $this->printer->printed);
        self::assertSame([['SHIRT-M', 6, 'booth']], array_map(static fn (Reservation $reservation): array => [$reservation->sku, $reservation->quantity, $reservation->location], $this->repository->reservations));
        self::assertSame(['SHIRT-L' => 1], $this->conventions->toPrint($this->order(['SHIRT-M' => 1, 'SHIRT-L' => 1], 'web', $at), ['SHIRT-M' => 0, 'SHIRT-L' => 1], $at));
    }

    public function testWithNoConventionNearAShortStockGoesToPrintWhole(): void
    {
        $at = new DateTimeImmutable('2026-11-01T10:00:00Z');
        $this->stock->reserve($this->order(['SHIRT-M' => 7], null, $at), $at);

        self::assertSame([['2026-0001', 'SHIRT-M', 7]], $this->printer->printed);
        self::assertSame([], $this->repository->reservations);
    }

    public function testAnOrderOfAnotherChannelTakesFromStockEvenWhileAConventionIsNear(): void
    {
        $at = new DateTimeImmutable('2026-12-20T10:00:00Z');
        $order = $this->order(['SHIRT-M' => 2], 'till', $at);

        self::assertSame([], $this->conventions->toPrint($order, ['SHIRT-M' => 2], $at));
        $this->stock->reserve($order, $at);
        self::assertSame([], $this->printer->printed);
        self::assertSame(4, $this->stock->available('SHIRT-M'));
    }

    public function testAnOrderSentToPrintIsNotRoutedAgain(): void
    {
        $at = new DateTimeImmutable('2026-12-01T10:00:00Z');
        $order = $this->order(['SHIRT-M' => 2], 'web', $at);
        $this->stock->reserve($order, $at);
        $this->stock->reserve($order, new DateTimeImmutable('2027-02-01T10:00:00Z'));

        $this->conventions->print('2026-0001', ['SHIRT-M' => 2, 'SHIRT-L' => 1], $at);

        self::assertSame([['2026-0001', 'SHIRT-M', 2], ['2026-0001', 'SHIRT-L', 1]], $this->printer->printed);
        self::assertSame([], $this->repository->reservations);
        self::assertSame([['SHIRT-M', 2], ['SHIRT-L', 1]], array_map(static fn (PrintJob $job): array => [$job->sku, $job->quantity], $this->repository->printJobsOf('2026-0001')));
    }

    public function testAnOrderWithoutConventionItemsIsNotRouted(): void
    {
        $at = new DateTimeImmutable('2026-12-01T10:00:00Z');

        self::assertSame([], $this->conventions->toPrint($this->order(['DICE' => 1], 'web', $at), ['DICE' => 1], $at));
        self::assertFalse($this->conventions->routed('2026-0001'));
    }

    public function testTheWindowIsThirtyDaysUnlessConfigured(): void
    {
        $conventions = new ConventionStock($this->repository, $this->repository, $this->repository);

        self::assertCount(1, $conventions->near(new DateTimeImmutable('2026-11-20T09:00:00Z')));
        self::assertSame([], $conventions->near(new DateTimeImmutable('2026-11-19T09:00:00Z')));
    }

    public function testEnteringAgainChangesTheQuantityEnteredBefore(): void
    {
        $before = $this->repository->quantity('fall-con', 'SHIRT-M');

        $this->conventions->enter('fall-con', 'SHIRT-M', 25);

        self::assertSame($before, $this->repository->quantity('fall-con', 'SHIRT-M'));
        self::assertSame(25, $before?->quantity());
    }

    public function testAQuantityEnteredIsTheQuantityPlanned(): void
    {
        $convention = Convention::of('spring', 'Spring Con', new DateTimeImmutable('2027-04-10'), new DateTimeImmutable('2027-04-11'));

        self::assertSame(7, ConventionQuantity::of($convention, 'SHIRT-S', 7)->quantity());
    }

    public function testTheShortfallLeavesOutTheSkusFullyOnTheShelfAndAddsUpTheLocations(): void
    {
        $this->stock->receive('SHIRT-M', 'warehouse', 14, new DateTimeImmutable('2026-11-02'));
        $this->stock->receive('SHIRT-L', 'booth', 4, new DateTimeImmutable('2026-11-02'));
        $this->stock->receive('SHIRT-L', 'warehouse', 5, new DateTimeImmutable('2026-11-02'));

        self::assertSame(['SHIRT-L' => 1], $this->conventions->shortfall('fall-con'));

        $this->stock->receive('SHIRT-M', 'warehouse', 1, new DateTimeImmutable('2026-11-03'));
        $this->stock->receive('SHIRT-L', 'booth', 1, new DateTimeImmutable('2026-11-03'));
        self::assertSame([], $this->conventions->shortfall('fall-con'));
    }

    public function testEachShortSkuKeepsItsOwnShortfall(): void
    {
        $this->stock->receive('SHIRT-M', 'warehouse', 1, new DateTimeImmutable('2026-11-02'));
        $this->stock->receive('SHIRT-L', 'warehouse', 3, new DateTimeImmutable('2026-11-02'));

        self::assertSame(['SHIRT-L' => 7, 'SHIRT-M' => 13], $this->conventions->shortfall('fall-con'));
    }

    public function testEveryConventionItemOfAnOrderGoesToPrintAndNumericSkusToo(): void
    {
        $at = new DateTimeImmutable('2026-12-01T10:00:00Z');
        $this->conventions->enter('fall-con', '1001', 5);
        $order = $this->order(['SHIRT-M' => 2, 'SHIRT-L' => 1, '1001' => 3], 'web', $at);

        self::assertSame(['1001' => 3, 'SHIRT-L' => 1, 'SHIRT-M' => 2], $this->conventions->toPrint($order, ['SHIRT-M' => 2, 'SHIRT-L' => 1, '1001' => 3], $at));

        $this->stock->reserve($order, $at);

        self::assertSame([['2026-0001', '1001', 3], ['2026-0001', 'SHIRT-L', 1], ['2026-0001', 'SHIRT-M', 2]], $this->printer->printed);
        self::assertSame([], $this->repository->levelLookups);
        self::assertSame([['1001', 3], ['SHIRT-L', 1], ['SHIRT-M', 2]], array_map(static fn (PrintJob $job): array => [$job->sku, $job->quantity], $this->repository->printJobsOf('2026-0001')));
    }

    public function testANumericConventionItemOnTheShelfComesFromStock(): void
    {
        $at = new DateTimeImmutable('2026-11-01T10:00:00Z');
        $this->conventions->enter('fall-con', '1001', 5);
        $this->stock->receive('1001', 'warehouse', 3, $at);
        $this->stock->reserve($this->order(['1001' => 3], 'web', $at), $at);

        self::assertSame([], $this->printer->printed);
        self::assertSame([['1001', 3]], array_map(static fn (Reservation $reservation): array => [$reservation->sku, $reservation->quantity], $this->repository->reservations));
    }

    public function testAnItemOfZeroUnitsIsNotPrintedWhenNothingIsOnTheShelf(): void
    {
        $at = new DateTimeImmutable('2026-11-01T10:00:00Z');

        self::assertSame([], $this->conventions->toPrint($this->order(['SHIRT-L' => 1], 'web', $at), ['SHIRT-L' => 0], $at));
    }

    public function testAnOrderWithoutConventionItemsAsksNothingAboutTheCalendar(): void
    {
        $at = new DateTimeImmutable('2026-12-01T10:00:00Z');

        self::assertSame([], $this->conventions->toPrint($this->order(['DICE' => 1], 'web', $at), ['DICE' => 1], $at));
        self::assertSame(0, $this->repository->meetingLookups);
    }

    public function testAnOrderWithoutTrackedLinesAsksNothingAboutConventions(): void
    {
        $at = new DateTimeImmutable('2026-12-01T10:00:00Z');
        $order = Order::open('2026-0001', Currency::of('CAD'));
        $price = Money::of(2_500, Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('offer', 'GIFT'), 'Gift', $price, 'goods'), 1, $price);
        $order->place($at);

        $this->stock->reserve($order, $at);

        self::assertSame(0, $this->repository->conventionLookups);
    }

    public function testAnItemsAlreadySentIsNotSentAgainWhileTheOthersAre(): void
    {
        $at = new DateTimeImmutable('2026-12-01T10:00:00Z');
        $this->conventions->print('2026-0001', ['SHIRT-M' => 2], $at);
        $this->conventions->print('2026-0001', ['SHIRT-M' => 2, 'SHIRT-L' => 1], $at);

        self::assertSame([['2026-0001', 'SHIRT-M', 2], ['2026-0001', 'SHIRT-L', 1]], $this->printer->printed);
    }

    public function testTheWindowIsBounded(): void
    {
        new ConventionStock($this->repository, $this->repository, $this->repository, 0);
        new ConventionStock($this->repository, $this->repository, $this->repository, 366);

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A convention is near within 0 to 366 days; 367 is refused.');

        new ConventionStock($this->repository, $this->repository, $this->repository, 367);
    }

    public function testANegativeWindowIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        new ConventionStock($this->repository, $this->repository, $this->repository, -1);
    }

    /**
     * @param array<int|string, int> $lines
     */
    private function order(array $lines, ?string $channel, DateTimeImmutable $at): Order
    {
        $order = Order::open('2026-0001', Currency::of('CAD'));
        $order->changeChannel($channel);

        $price = Money::of(2_500, Currency::of('CAD'));

        foreach ($lines as $sku => $quantity) {
            $order->add(Sellable::of(SellableReference::of('product', (string) $sku), (string) $sku, $price, 'goods'), $quantity, $price);
        }

        $order->place($at);

        return $order;
    }
}
