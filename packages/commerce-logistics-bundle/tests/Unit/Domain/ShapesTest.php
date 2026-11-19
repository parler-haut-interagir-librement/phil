<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\CommerceLogisticsBundle\Domain\Model\Convention;
use Phil\CommerceLogisticsBundle\Domain\Model\ConventionQuantity;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\PurchaseOrder;
use Phil\CommerceLogisticsBundle\Domain\Model\SerialNumber;
use Phil\CommerceLogisticsBundle\Domain\Model\StockLevel;
use Phil\CommerceLogisticsBundle\Domain\Model\StockPolicy;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The text each model takes as a code, a SKU or a number: visible ASCII, nothing after the end, at most its length.
 */
#[CoversClass(StockPolicy::class)]
#[CoversClass(SerialNumber::class)]
#[CoversClass(PurchaseOrder::class)]
#[CoversClass(ConventionQuantity::class)]
#[CoversClass(Convention::class)]
#[CoversClass(StockLevel::class)]
final class ShapesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function skusRefused(): iterable
    {
        yield 'a trailing line feed' => ["DICE\n"];
        yield 'a space' => ['DI CE'];
        yield 'an empty text' => [''];
        yield 'sixty-five characters' => [str_repeat('d', 65)];
    }

    #[DataProvider('skusRefused')]
    public function testAStockPolicyTakesAVisibleSku(string $sku): void
    {
        $this->expectException(InvalidValue::class);

        StockPolicy::of($sku);
    }

    #[DataProvider('skusRefused')]
    public function testAConventionQuantityTakesAVisibleSku(string $sku): void
    {
        $this->expectException(InvalidValue::class);

        ConventionQuantity::of($this->convention(), $sku, 1);
    }

    #[DataProvider('skusRefused')]
    public function testAPurchaseOrderTakesVisibleSkus(string $sku): void
    {
        $this->expectException(InvalidValue::class);

        PurchaseOrder::of('PO-1', 'Dice Works', Location::of('warehouse', 'Warehouse'), [$sku => 1], new DateTimeImmutable('2026-11-12'));
    }

    public function testAPurchaseOrderTakesAVisibleNumberAndASupplierOfMultibyteCharacters(): void
    {
        $location = Location::of('warehouse', 'Warehouse');
        $at = new DateTimeImmutable('2026-11-12');

        self::assertSame('PO-1', PurchaseOrder::of('PO-1', str_repeat('é', 255), $location, ['DICE' => 1], $at)->number);

        foreach (["PO-1\n", str_repeat('p', 65)] as $number) {
            try {
                PurchaseOrder::of($number, 'Dice Works', $location, ['DICE' => 1], $at);
                self::fail('A number of another shape was taken.');
            } catch (InvalidValue $invalidValue) {
                self::assertStringContainsString('is not a purchase order number', $invalidValue->getMessage());
            }
        }
    }

    public function testAnUnorderedSkuHasNothingOutstanding(): void
    {
        $order = PurchaseOrder::of('PO-1', 'Dice Works', Location::of('warehouse', 'Warehouse'), ['DICE' => 3], new DateTimeImmutable('2026-11-12'));

        self::assertSame([3, 0], [$order->outstanding('DICE'), $order->outstanding('MAP')]);
    }

    public function testASerialIsVisibleAsciiOfAtMostOneHundredTwentyEightCharacters(): void
    {
        $level = StockLevel::of('CONSOLE', Location::of('warehouse', 'Warehouse'));
        $at = new DateTimeImmutable('2026-11-12');

        self::assertSame(128, \strlen(SerialNumber::of($level, str_repeat('s', 128), $at)->serial));

        foreach (["SN-1\n", str_repeat('s', 129)] as $serial) {
            try {
                SerialNumber::of($level, $serial, $at);
                self::fail('A serial of another shape was taken.');
            } catch (InvalidValue) {
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function codesRefused(): iterable
    {
        yield 'a trailing line feed' => ["fall\n"];
        yield 'a space' => ['fa ll'];
        yield 'sixty-five characters' => [str_repeat('a', 65)];
    }

    #[DataProvider('codesRefused')]
    public function testAConventionTakesALowercaseCode(string $code): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('is not a convention code');

        Convention::of($code, 'Fall Con', new DateTimeImmutable('2026-12-20'), new DateTimeImmutable('2026-12-21'));
    }

    public function testAConventionNameCountsCharactersNotBytes(): void
    {
        $convention = Convention::of('fall', str_repeat('é', 255), new DateTimeImmutable('2026-12-20'), new DateTimeImmutable('2026-12-21'));

        self::assertSame(255, mb_strlen($convention->name));
    }

    public function testAConventionIsRescheduledToWholeDays(): void
    {
        $convention = $this->convention();
        $convention->reschedule(new DateTimeImmutable('2027-01-05 18:30'), new DateTimeImmutable('2027-01-06 09:15'));

        self::assertSame(['2027-01-05 00:00', '2027-01-06 00:00'], [$convention->startsOn()->format('Y-m-d H:i'), $convention->endsOn()->format('Y-m-d H:i')]);
    }

    public function testAConventionMeetsTheDaysAroundItsOwn(): void
    {
        $convention = $this->convention();

        self::assertTrue($convention->meets(new DateTimeImmutable('2026-12-21 23:59'), new DateTimeImmutable('2026-12-25')));
        self::assertTrue($convention->meets(new DateTimeImmutable('2026-12-01'), new DateTimeImmutable('2026-12-20 00:00')));
        self::assertFalse($convention->meets(new DateTimeImmutable('2026-12-22 00:00'), new DateTimeImmutable('2026-12-25')));
        self::assertFalse($convention->meets(new DateTimeImmutable('2026-12-01'), new DateTimeImmutable('2026-12-19 23:59')));
    }

    private function convention(): Convention
    {
        return Convention::of('fall', 'Fall Con', new DateTimeImmutable('2026-12-20'), new DateTimeImmutable('2026-12-21'));
    }
}
