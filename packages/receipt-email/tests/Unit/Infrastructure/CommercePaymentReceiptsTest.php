<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Receipt;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\Issuer;
use Phil\CommerceBundle\Domain\ValueObject\LineConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\ReceiptEmail\Domain\Payment\PaymentDocument;
use Phil\ReceiptEmail\Domain\ValueObject\ReceiptCharge;
use Phil\ReceiptEmail\Domain\ValueObject\ReceiptLine;
use Phil\ReceiptEmail\Domain\ValueObject\TaxAmount;
use Phil\ReceiptEmail\Infrastructure\Commerce\CommercePaymentReceipts;
use Phil\ReceiptEmail\Tests\Support\Commerce;
use Phil\ReceiptEmail\Tests\Support\InMemoryReceipts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CommercePaymentReceipts::class)]
final class CommercePaymentReceiptsTest extends TestCase
{
    public function testAReceiptIsReadAsIssued(): void
    {
        $receipt = new CommercePaymentReceipts(new InMemoryReceipts(Commerce::receipt()))->find('R-000042');

        self::assertNotNull($receipt);
        self::assertSame(PaymentDocument::Receipt, $receipt->document);
        self::assertSame('R-000042', $receipt->number);
        self::assertSame('A-1001', $receipt->orderNumber);
        self::assertNull($receipt->creditedReceipt);
        self::assertSame('2026-11-20 14:30', $receipt->issuedAt->format('Y-m-d H:i'));
        self::assertSame('The Shop', $receipt->issuer->name);
        self::assertSame(['12 Main Street', 'Montréal QC  H2X 1Y4'], $receipt->issuer->address);
        self::assertSame(['GST' => '123456789 RT0001', 'QST' => '1234567890 TQ0001'], $receipt->taxNumbers);
        self::assertSame(4_500, $receipt->itemsTotal->minorUnits);
        self::assertSame(5_674, $receipt->total->minorUnits);
        self::assertSame([['Delivery', 1_000], ['Welcome', -500]], array_map(static fn (ReceiptCharge $charge): array => [$charge->label, $charge->amount->minorUnits], $receipt->charges));
        self::assertSame([['GST', 225, false], ['QST', 449, false]], $this->taxes($receipt->taxes));
        self::assertSame([
            ['Tea', 3, 500, 1_500, [['GST', 75, false], ['QST', 150, false]], [], []],
            ['Teapot', 1, 3_000, 3_000, [['GST', 150, false], ['QST', 299, false]], ['Colour: Blue'], ['Glaze']],
        ], array_map(fn (ReceiptLine $line): array => [$line->description, $line->quantity, $line->unitPrice->minorUnits, $line->amount->minorUnits, $this->taxes($line->taxes), $line->options, $line->pending], $receipt->lines));
    }

    public function testAnUnknownNumberIsNotFound(): void
    {
        self::assertNull(new CommercePaymentReceipts(new InMemoryReceipts())->find('R-404'));
    }

    public function testTaxesIncludedInThePricesAreMarked(): void
    {
        $receipt = CommercePaymentReceipts::read(Commerce::receipt(taxIncluded: true));

        self::assertSame([['GST', 225, true], ['QST', 449, true]], $this->taxes($receipt->taxes));
        self::assertSame([['GST', 75, true], ['QST', 150, true]], $this->taxes($receipt->lines[0]->taxes));
        self::assertSame(5_000, $receipt->total->minorUnits);
        self::assertSame([['Delivery', 1_000], ['Welcome', -500]], array_map(static fn (ReceiptCharge $charge): array => [$charge->label, $charge->amount->minorUnits], $receipt->charges), 'A neutral tax is not a charge.');
    }

    public function testACreditNoteIsNegative(): void
    {
        $note = CommercePaymentReceipts::read(Commerce::creditNote());

        self::assertSame(PaymentDocument::CreditNote, $note->document);
        self::assertSame('C-000007', $note->number);
        self::assertSame('R-000042', $note->creditedReceipt);
        self::assertSame(-4_500, $note->itemsTotal->minorUnits);
        self::assertSame(-5_674, $note->total->minorUnits);
        self::assertSame(-1_500, $note->lines[0]->amount->minorUnits);
        self::assertSame(500, $note->lines[0]->unitPrice->minorUnits);
        self::assertSame([['GST', -75, false], ['QST', -150, false]], $this->taxes($note->lines[0]->taxes));
        self::assertSame([['GST', -225, false], ['QST', -449, false]], $this->taxes($note->taxes));
        self::assertSame([['Delivery', -1_000], ['Welcome', 500]], array_map(static fn (ReceiptCharge $charge): array => [$charge->label, $charge->amount->minorUnits], $note->charges));
    }

    public function testANeutralChargeAndATaxOfTheOrderAreNotOnALine(): void
    {
        $cad = Currency::of('CAD');
        $order = Order::open('A-2', $cad);
        $line = $order->add(Sellable::of(SellableReference::of('product', 'A'), 'A', Money::of(1_000, $cad), 'standard'), 1, Money::of(1_000, $cad), LineConfiguration::none());
        $order->add(Sellable::of(SellableReference::of('product', 'B'), 'B', Money::of(100, $cad), 'standard'), 1, Money::of(100, $cad));
        $order->replaceAdjustments(AdjustmentType::Fee, [Adjustment::of(AdjustmentType::Fee, 'Shown only', Money::of(100, $cad), null, true)]);
        $order->replaceAdjustments(AdjustmentType::Tax, [Adjustment::of(AdjustmentType::Tax, 'GST', Money::of(50, $cad)), Adjustment::of(AdjustmentType::Tax, 'GST', Money::of(5, $cad), SellableReference::of('product', 'B'))]);
        $order->place(new DateTimeImmutable('2026-11-20'));

        $receipt = CommercePaymentReceipts::read(Receipt::issue('R-2', $order, Issuer::of('Shop'), new DateTimeImmutable('2026-11-20')));

        self::assertSame([], $receipt->charges);
        self::assertSame([], $receipt->lines[0]->taxes, 'A tax of the order or of another line is not on this line.');
        self::assertSame([['GST', 5, false]], $this->taxes($receipt->lines[1]->taxes));
        self::assertSame([['GST', 55, false]], $this->taxes($receipt->taxes));
        self::assertSame('product:A', $line->reference->value());
        self::assertSame([], $receipt->taxNumbers);
    }

    /**
     * @param list<TaxAmount> $taxes
     *
     * @return list<array{string, int, bool}>
     */
    private function taxes(array $taxes): array
    {
        return array_map(static fn (TaxAmount $tax): array => [$tax->code, $tax->amount->minorUnits, $tax->included], $taxes);
    }
}
