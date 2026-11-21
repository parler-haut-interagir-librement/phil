<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceLogisticsBundle\Application\Purchasing;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\PurchaseOrder;
use Phil\CommerceLogisticsBundle\Domain\Purchasing\PurchaseLine;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\InMemoryLogistics;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(PurchaseLine::class)]
#[CoversClass(PurchaseOrder::class)]
#[CoversClass(Purchasing::class)]
final class PurchaseLineTest extends TestCase
{
    private InMemoryLogistics $repository;

    private Purchasing $purchasing;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->repository = new InMemoryLogistics();
        $this->repository->addLocation(Location::of('warehouse', 'Warehouse'));

        $this->purchasing = new Purchasing($this->repository, $this->repository, new Stock($this->repository, $this->repository));
        $this->at = new DateTimeImmutable('2026-11-21T10:00:00Z');
    }

    public function testALineCarriesItsUnitCostAndItsOwnerToTheReceipt(): void
    {
        $this->purchasing->order('PO-1', 'Books & Co', 'warehouse', [
            'DICE' => 10,
            'BOOK' => PurchaseLine::of(5, Money::of(1_250, Currency::of('CAD')), 'consignor-17'),
            '1234' => PurchaseLine::of(2, Money::of(0, Currency::of('JPY'))),
            'ZINE' => PurchaseLine::of(3, owner: 'Zoë Café'),
            'CARD' => PurchaseLine::of(4),
        ], $this->at);

        $order = $this->purchasing->receive('PO-1', ['BOOK' => 2, '1234' => 1], $this->at);

        self::assertSame(['DICE' => 10, 'BOOK' => 5, 1234 => 2, 'ZINE' => 3, 'CARD' => 4], $order->ordered);
        self::assertSame([
            'DICE' => [10, null, null],
            'BOOK' => [5, '12.50 CAD', 'consignor-17'],
            '1234' => [2, '0 JPY', null],
            'ZINE' => [3, null, 'Zoë Café'],
            'CARD' => [4, null, null],
            'MAP' => null,
        ], array_map(fn (string $sku): ?array => $this->line($order, $sku), ['DICE' => 'DICE', 'BOOK' => 'BOOK', '1234' => '1234', 'ZINE' => 'ZINE', 'CARD' => 'CARD', 'MAP' => 'MAP']));
        self::assertSame(2, $this->repository->level('BOOK', 'warehouse')?->onHand());
        self::assertSame(['BOOK' => ['cost' => 1_250, 'currency' => 'CAD', 'owner' => 'consignor-17'], 1234 => ['cost' => 0, 'currency' => 'JPY', 'owner' => null], 'ZINE' => ['cost' => null, 'currency' => null, 'owner' => 'Zoë Café']], $this->terms($order));
    }

    public function testAnOrderOfUnitsOnlyStoresNoTerms(): void
    {
        $order = $this->purchasing->order('PO-2', 'Dice Works', 'warehouse', ['DICE' => 10, 'CARD' => PurchaseLine::of(4)], $this->at);

        self::assertNull($this->terms($order));
    }

    public function testTheUnitsOfALineAreCheckedByTheOrderToo(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The purchase order PO-3 orders one unit or more of each SKU');

        $this->purchasing->order('PO-3', 'Dice Works', 'warehouse', ['DICE' => 0, 'CARD' => PurchaseLine::of(4)], $this->at);
    }

    /**
     * @param array{int, Money|null, string|null} $line
     */
    #[DataProvider('invalidLines')]
    public function testALineIsRefusedOutsideItsBounds(array $line, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        PurchaseLine::of(...$line);
    }

    /**
     * @return iterable<string, array{array{int, Money|null, string|null}, string}>
     */
    public static function invalidLines(): iterable
    {
        $owner = 'An owner has 1 to 64 characters, none of them a control character.';

        yield 'no unit' => [[0, null, null], 'A purchase order line orders one unit or more; 0 is not.'];
        yield 'a negative cost' => [[1, Money::of(-1, Currency::of('CAD')), null], 'A unit cost is zero or more; -0.01 CAD is not.'];
        yield 'an empty owner' => [[1, null, ''], $owner];
        yield 'a blank owner' => [[1, null, '   '], $owner];
        yield 'a long owner' => [[1, null, str_repeat('é', 65)], $owner];
        yield 'a control character' => [[1, null, "owner\n"], $owner];
        yield 'a delete character' => [[1, null, "owner\x7F"], $owner];
    }

    public function testAnOwnerOfSixtyFourCharactersIsKept(): void
    {
        self::assertSame(str_repeat('é', 64), PurchaseLine::of(1, null, str_repeat('é', 64))->owner);
    }

    /**
     * @return array{int, string|null, string|null}|null
     */
    private function line(PurchaseOrder $order, string $sku): ?array
    {
        $line = $order->line($sku);

        return $line instanceof PurchaseLine ? [$line->units, $line->unitCost instanceof Money ? $line->unitCost->toDecimal().' '.$line->unitCost->currency->code : null, $line->owner] : null;
    }

    private function terms(PurchaseOrder $order): mixed
    {
        return new ReflectionProperty(PurchaseOrder::class, 'lineTerms')->getValue($order);
    }
}
