<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\Model;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Exception\ReceiptRefused;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Receipt;
use Phil\CommerceBundle\Domain\Receipt\DocumentKind;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\Issuer;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Receipt::class)]
#[CoversClass(ReceiptRefused::class)]
#[CoversClass(Issuer::class)]
final class ReceiptTest extends TestCase
{
    public function testAReceiptCopiesThePlacedOrder(): void
    {
        $order = $this->order();
        $at = new DateTimeImmutable('2026-11-03T16:00:00Z');
        $receipt = Receipt::issue('R-00000001', $order, $this->issuer(), $at);

        self::assertSame('R-00000001', $receipt->number);
        self::assertSame(DocumentKind::Receipt, $receipt->kind);
        self::assertSame('2026-0001', $receipt->orderNumber);
        self::assertNull($receipt->creditedReceipt);
        self::assertSame($at, $receipt->issuedAt);
        self::assertSame('CAD', $receipt->currency->code);
        self::assertSame('Le Magasin', $receipt->issuer->name);
        self::assertSame(['1 rue Principale', 'Québec QC'], $receipt->issuer->address);
        self::assertSame(['GST' => '123456789 RT0001', 'QST' => '1234567890 TQ0001'], $receipt->issuer->taxNumbers);
        self::assertSame(['product:dice', 'product:pdf'], array_map(static fn (OrderLine $line): string => $line->reference->value(), $receipt->lines()));
        self::assertSame(3_000, $receipt->itemsTotal()->minorUnits);
        self::assertSame(1_449, $receipt->adjustmentsTotal()->minorUnits);
        self::assertSame(449, $receipt->adjustmentsTotal(AdjustmentType::Tax)->minorUnits);
        self::assertSame(1_000, $receipt->adjustmentsTotal(AdjustmentType::Shipping)->minorUnits);
        self::assertSame(['GST' => 150, 'QST' => 299, 'VAT' => 50], array_map(static fn (Money $tax): int => $tax->minorUnits, $receipt->taxes()));
        self::assertTrue($receipt->total()->equals($order->total()));
        self::assertCount(\count($order->adjustments()), $receipt->adjustments());
    }

    /**
     * A change to the order after the receipt never reaches it.
     */
    public function testAReceiptKeepsWhatWasSold(): void
    {
        $order = $this->order();
        $receipt = Receipt::issue('R-1', $order, $this->issuer(), new DateTimeImmutable());
        $order->cancel(new DateTimeImmutable());

        self::assertSame(4_449, $receipt->total()->minorUnits);
    }

    public function testACartGetsNoReceipt(): void
    {
        $order = Order::open('7', Currency::of('CAD'));

        $this->expectException(ReceiptRefused::class);
        $this->expectExceptionMessageIsOrContains('The order 7 is not placed: only a placed order gets a receipt.');

        Receipt::issue('R-1', $order, $this->issuer(), new DateTimeImmutable());
    }

    public function testAFullCreditNoteReversesEverything(): void
    {
        $receipt = Receipt::issue('R-1', $this->order(), $this->issuer(), new DateTimeImmutable());
        $at = new DateTimeImmutable('2026-11-10T10:00:00Z');
        $note = Receipt::credit('CN-1', $receipt, [], $at);

        self::assertSame('CN-1', $note->number);
        self::assertSame(DocumentKind::CreditNote, $note->kind);
        self::assertSame('R-1', $note->creditedReceipt);
        self::assertSame('2026-0001', $note->orderNumber);
        self::assertSame($at, $note->issuedAt);
        self::assertSame('Le Magasin', $note->issuer->name);
        self::assertSame(-3_000, $note->itemsTotal()->minorUnits);
        self::assertSame(['GST' => -150, 'QST' => -299, 'VAT' => -50], array_map(static fn (Money $tax): int => $tax->minorUnits, $note->taxes()));
        self::assertTrue($note->total()->equals($receipt->total()->negate()));
        self::assertSame(-1_000, $note->adjustmentsTotal(AdjustmentType::Shipping)->minorUnits);
    }

    /**
     * Lines credited one by one, each with its own adjustments; the order's own adjustments go with the last.
     */
    public function testLinesAreCreditedOneByOneAndTheOrdersAdjustmentsWithTheLast(): void
    {
        $receipt = Receipt::issue('R-1', $this->order(), $this->issuer(), new DateTimeImmutable());
        $first = Receipt::credit('CN-1', $receipt, [], new DateTimeImmutable(), [SellableReference::of('product', 'dice')]);

        self::assertSame(['product:dice'], array_map(static fn (OrderLine $line): string => $line->reference->value(), $first->lines()));
        self::assertSame(-2_000, $first->itemsTotal()->minorUnits);
        self::assertSame(['GST' => -100, 'QST' => -199, 'VAT' => -50], array_map(static fn (Money $tax): int => $tax->minorUnits, $first->taxes()));
        self::assertTrue($first->adjustmentsTotal(AdjustmentType::Shipping)->isZero());

        $second = Receipt::credit('CN-2', $receipt, [$first], new DateTimeImmutable());

        self::assertSame(['product:pdf'], array_map(static fn (OrderLine $line): string => $line->reference->value(), $second->lines()));
        self::assertSame(-1_000, $second->adjustmentsTotal(AdjustmentType::Shipping)->minorUnits);
        self::assertTrue($first->total()->add($second->total())->equals($receipt->total()->negate()));

        try {
            Receipt::credit('CN-3', $receipt, [$first, $second], new DateTimeImmutable());
            self::fail('A third note was issued.');
        } catch (ReceiptRefused $receiptRefused) {
            self::assertSame('The receipt R-1 has nothing left to credit.', $receiptRefused->getMessage());
        }
    }

    public function testALineAlreadyCreditedIsRefused(): void
    {
        $receipt = Receipt::issue('R-1', $this->order(), $this->issuer(), new DateTimeImmutable());
        $first = Receipt::credit('CN-1', $receipt, [], new DateTimeImmutable(), [SellableReference::of('product', 'dice')]);

        $this->expectException(ReceiptRefused::class);
        $this->expectExceptionMessageIsOrContains('The receipt R-1 has no line product:dice left to credit.');

        Receipt::credit('CN-2', $receipt, [$first], new DateTimeImmutable(), [SellableReference::of('product', 'pdf'), SellableReference::of('product', 'dice')]);
    }

    public function testALineTheReceiptLacksIsRefused(): void
    {
        $receipt = Receipt::issue('R-1', $this->order(), $this->issuer(), new DateTimeImmutable());

        $this->expectException(ReceiptRefused::class);
        $this->expectExceptionMessageIsOrContains('The receipt R-1 has no line product:meeple left to credit.');

        Receipt::credit('CN-1', $receipt, [], new DateTimeImmutable(), [SellableReference::of('product', 'meeple')]);
    }

    public function testNoLineNamedIsNothingToCredit(): void
    {
        $receipt = Receipt::issue('R-1', $this->order(), $this->issuer(), new DateTimeImmutable());

        $this->expectException(ReceiptRefused::class);
        $this->expectExceptionMessageIsOrContains('The receipt R-1 has nothing left to credit.');

        Receipt::credit('CN-1', $receipt, [], new DateTimeImmutable(), []);
    }

    public function testACreditNoteIsNotCredited(): void
    {
        $note = Receipt::credit('CN-1', Receipt::issue('R-1', $this->order(), $this->issuer(), new DateTimeImmutable()), [], new DateTimeImmutable());

        $this->expectException(ReceiptRefused::class);
        $this->expectExceptionMessageIsOrContains('CN-1 is a credit note: only a receipt is credited.');

        Receipt::credit('CN-2', $note, [], new DateTimeImmutable());
    }

    public function testRefusalsAreDomainErrors(): void
    {
        self::assertInstanceOf(DomainError::class, ReceiptRefused::alreadyIssued('1', 'R-1'));
        self::assertSame('The order 1 already has the receipt R-1.', ReceiptRefused::alreadyIssued('1', 'R-1')->getMessage());
    }

    public function testAnIssuerIsKeptAsAnArray(): void
    {
        $issuer = Issuer::of('Le Magasin');

        self::assertSame([], $issuer->address);
        self::assertSame([], $issuer->taxNumbers);
        self::assertSame(['name' => 'Le Magasin', 'address' => [], 'tax_numbers' => []], $issuer->toArray());
        self::assertSame($this->issuer()->toArray(), Issuer::fromArray($this->issuer()->toArray())->toArray());
    }

    /**
     * @return iterable<string, array{string, list<string>, array<string, string>, string}>
     */
    public static function invalidIssuers(): iterable
    {
        yield 'no name' => ['', [], [], 'An issuer has a name, which the receipt shows.'];
        yield 'blank name' => [' ', [], [], 'An issuer has a name, which the receipt shows.'];
        yield 'blank address line' => ['Shop', ['1 rue', ' '], [], 'An address line or a registration number is never blank.'];
        yield 'blank registration number' => ['Shop', [], ['GST' => ''], 'An address line or a registration number is never blank.'];
    }

    /**
     * @param list<string>          $address
     * @param array<string, string> $taxNumbers
     */
    #[DataProvider('invalidIssuers')]
    public function testAnInvalidIssuerIsRefused(string $name, array $address, array $taxNumbers, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        Issuer::of($name, $address, $taxNumbers);
    }

    private function issuer(): Issuer
    {
        return Issuer::of('Le Magasin', ['1 rue Principale', 'Québec QC'], ['GST' => '123456789 RT0001', 'QST' => '1234567890 TQ0001']);
    }

    /**
     * Two lines, GST and QST on each, a neutral VAT shown on the first, and shipping on the order.
     */
    private function order(): Order
    {
        $cad = static fn (int $minorUnits): Money => Money::of($minorUnits, Currency::of('CAD'));
        $dice = SellableReference::of('product', 'dice');
        $pdf = SellableReference::of('product', 'pdf');
        $order = Order::open('2026-0001', Currency::of('CAD'));
        $order->add(Sellable::of($dice, 'Dice', $cad(1_000), 'goods'), 2, $cad(1_000));
        $order->add(Sellable::of($pdf, 'PDF', $cad(1_000), 'digital'), 1, $cad(1_000));
        $order->replaceAdjustments(AdjustmentType::Tax, [
            Adjustment::of(AdjustmentType::Tax, 'GST', $cad(100), $dice),
            Adjustment::of(AdjustmentType::Tax, 'QST', $cad(199), $dice),
            Adjustment::of(AdjustmentType::Tax, 'VAT', $cad(50), $dice, neutral: true),
            Adjustment::of(AdjustmentType::Tax, 'GST', $cad(50), $pdf),
            Adjustment::of(AdjustmentType::Tax, 'QST', $cad(100), $pdf),
        ]);
        $order->replaceAdjustments(AdjustmentType::Shipping, [Adjustment::of(AdjustmentType::Shipping, 'Post', $cad(1_000))]);
        $order->place(new DateTimeImmutable());

        return $order;
    }
}
