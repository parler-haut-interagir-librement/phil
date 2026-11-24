<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Domain;

use Closure;
use Phil\CommerceConsignmentBundle\Domain\Exception\InvalidValue;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ConsignmentTerms;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Margin;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Receipt;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ReceiptLine;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Sale;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\SaleLine;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\Fixtures;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Owner::class)]
#[CoversClass(ConsignmentTerms::class)]
#[CoversClass(ReceiptLine::class)]
#[CoversClass(Receipt::class)]
#[CoversClass(Margin::class)]
#[CoversClass(Sale::class)]
#[CoversClass(InvalidValue::class)]
final class ValueObjectsTest extends TestCase
{
    public function testTheShopAndAConsignor(): void
    {
        $shop = Owner::shop();
        $author = Owner::consignor('ann');

        self::assertFalse($shop->isConsigned());
        self::assertTrue($author->isConsigned());
        self::assertSame('', $shop->key());
        self::assertSame('ann', $author->key());
        self::assertTrue(Owner::fromKey('')->equals($shop));
        self::assertTrue(Owner::fromKey('ann')->equals($author));
        self::assertFalse($author->equals($shop));
        self::assertFalse($author->equals(Owner::consignor('Ann')));
        self::assertSame('the shop', $shop->describe());
        self::assertSame('the consignor ann', $author->describe());
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function refusals(): iterable
    {
        yield 'an empty consignor' => [static fn (): Owner => Owner::consignor(' '), 'A consignor has 1 to 64 characters, none of them a control character; " " does not.'];
        yield 'a consignor of 65 characters' => [static fn (): Owner => Owner::consignor(str_repeat('é', 65)), 'A consignor has 1 to 64 characters'];
        yield 'a control character' => [static fn (): Owner => Owner::consignor("a\nb"), 'A consignor has 1 to 64 characters'];
        yield 'a delete character' => [static fn (): Owner => Owner::consignor("a\x7Fb"), 'A consignor has 1 to 64 characters'];
        yield 'a negative share' => [static fn (): ConsignmentTerms => ConsignmentTerms::share(Fixtures::cad(-1)), "A consignor's share is zero or more; -0.01 is not."];
        yield 'a negative commission' => [static fn (): ConsignmentTerms => ConsignmentTerms::commission(-1), 'A commission is 0 to 1 000 000 parts per million; -1 is not.'];
        yield 'a commission above the whole' => [static fn (): ConsignmentTerms => ConsignmentTerms::commission(1_000_001), 'A commission is 0 to 1 000 000 parts per million; 1000001 is not.'];
        yield 'a negative cost' => [static fn (): ReceiptLine => ReceiptLine::purchased('A', 1, Fixtures::cad(-5)), 'A unit cost is zero or more; -0.05 is not.'];
        yield 'no unit bought' => [static fn (): ReceiptLine => ReceiptLine::purchased('A', 0, Fixtures::cad(5)), 'A quantity is one unit or more; 0 is not.'];
        yield 'no unit consigned' => [static fn (): ReceiptLine => ReceiptLine::consigned('A', 0, 'ann', ConsignmentTerms::commission(1)), 'A quantity is one unit or more; 0 is not.'];
        yield 'an empty SKU' => [static fn (): ReceiptLine => ReceiptLine::consigned('', 1, 'ann', ConsignmentTerms::commission(1)), 'A SKU has 1 to 64 characters'];
        yield 'an empty receipt' => [static fn (): Receipt => Receipt::of('DN', 'store', []), 'A receipt holds 1 to 500 lines; 0 were given.'];
        yield 'a receipt too long' => [static fn (): Receipt => Receipt::of('DN', 'store', array_fill(0, 501, ReceiptLine::purchased('A', 1, Fixtures::cad(1)))), 'A receipt holds 1 to 500 lines; 501 were given.'];
        yield 'a receipt without reference' => [static fn (): Receipt => Receipt::of('', 'store', [ReceiptLine::purchased('A', 1, Fixtures::cad(1))]), 'A reference has'];
        yield 'a receipt without location' => [static fn (): Receipt => Receipt::of('DN', '', [ReceiptLine::purchased('A', 1, Fixtures::cad(1))]), 'A location has'];
        yield 'an empty purchase order' => [static fn (): Receipt => Receipt::of('DN', 'store', [ReceiptLine::purchased('A', 1, Fixtures::cad(1))], ''), 'A purchase order has'];
    }

    /**
     * @param Closure(): mixed $make
     */
    #[DataProvider('refusals')]
    public function testRefusals(Closure $make, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);
        $make();
    }

    public function testTheLimitsAreAccepted(): void
    {
        self::assertSame(str_repeat('é', 64), Owner::consignor(str_repeat('é', 64))->consignor);
        self::assertSame(0, ConsignmentTerms::commission(0)->commissionPpm);
        self::assertSame(1_000_000, ConsignmentTerms::commission(1_000_000)->commissionPpm);
        self::assertSame(0, ConsignmentTerms::share(Fixtures::cad(0))->share?->minorUnits);
        self::assertSame(0, ReceiptLine::purchased('A', 1, Fixtures::cad(0))->unitCost?->minorUnits);
        self::assertCount(500, Receipt::of('DN', 'store', array_fill(0, 500, ReceiptLine::purchased('A', 1, Fixtures::cad(1))))->lines);
        self::assertSame(1, InvalidValue::checkQuantity(1));
    }

    public function testTermsAreAShareOrACommission(): void
    {
        $share = ConsignmentTerms::share(Fixtures::cad(1500));
        $commission = ConsignmentTerms::commission(300_000);

        self::assertTrue($share->isShare());
        self::assertNull($share->commissionPpm);
        self::assertFalse($commission->isShare());
        self::assertNull($commission->share);
    }

    public function testReceiptLinesSayWhoOwnsThem(): void
    {
        $bought = ReceiptLine::purchased('BOOK', 5, Fixtures::cad(1200));
        $consigned = ReceiptLine::consigned('BOOK', 5, 'ann', ConsignmentTerms::commission(300_000));

        self::assertSame(['BOOK', 5, '', 1200, null], [$bought->sku, $bought->quantity, $bought->owner->key(), $bought->unitCost?->minorUnits, $bought->terms]);
        self::assertSame(['BOOK', 5, 'ann', null, 300_000], [$consigned->sku, $consigned->quantity, $consigned->owner->key(), $consigned->unitCost, $consigned->terms?->commissionPpm]);
    }

    public function testAReceiptAddsTheUnitsOfEachSku(): void
    {
        $receipt = Receipt::of('DN-1', 'store', [
            ReceiptLine::purchased('BOOK', 5, Fixtures::cad(1200)),
            ReceiptLine::purchased('123', 1, Fixtures::cad(100)),
            ReceiptLine::consigned('BOOK', 4, 'ann', ConsignmentTerms::commission(300_000)),
        ], 'PO-1');

        self::assertSame(['BOOK' => 9, 123 => 1], $receipt->quantities());
        self::assertSame(['DN-1', 'store', 'PO-1'], [$receipt->reference, $receipt->location, $receipt->purchaseOrder]);
        self::assertNull(Receipt::of('DN-1', 'store', [ReceiptLine::purchased('BOOK', 5, Fixtures::cad(1200))])->purchaseOrder);
    }

    public function testAMarginAddsAndNegates(): void
    {
        $margin = Margin::of(Fixtures::cad(2000), Fixtures::cad(1200));
        $sum = $margin->add(Margin::of(Fixtures::cad(2000), Fixtures::cad(1500)));
        $negated = $margin->negate();

        self::assertSame(800, $margin->margin->minorUnits);
        self::assertSame([4000, 2700, 1300], [$sum->revenue->minorUnits, $sum->cost->minorUnits, $sum->margin->minorUnits]);
        self::assertSame([-2000, -1200, -800], [$negated->revenue->minorUnits, $negated->cost->minorUnits, $negated->margin->minorUnits]);
    }

    public function testASaleTotalsItsLines(): void
    {
        $sale = new Sale('ORD-1', 'BOOK', [
            new SaleLine('a', 'store', Owner::shop(), 2, Margin::of(Fixtures::cad(4000), Fixtures::cad(2400))),
            new SaleLine('b', 'store', Owner::consignor('ann'), 1, Margin::of(Fixtures::cad(2000), Fixtures::cad(1500))),
            new SaleLine('c', 'store', Owner::consignor('ann'), 3, Margin::of(Fixtures::cad(6000), Fixtures::cad(4500))),
            new SaleLine('d', 'store', Owner::consignor('bo'), 1, Margin::of(Fixtures::cad(2000), Fixtures::cad(1000))),
        ]);

        self::assertSame(7, $sale->quantity());
        self::assertSame(2, $sale->quantityOf(Owner::shop()));
        self::assertSame(4, $sale->quantityOf(Owner::consignor('ann')));
        self::assertSame(14000, $sale->margin()?->revenue->minorUnits);
        self::assertSame(['ann' => 6000, 'bo' => 1000], array_map(static fn (Money $money): int => $money->minorUnits, $sale->owedToConsignors()));
        self::assertNull(new Sale('ORD-2', 'BOOK', [])->margin());
        self::assertSame([], new Sale('ORD-2', 'BOOK', [])->owedToConsignors());
    }
}
