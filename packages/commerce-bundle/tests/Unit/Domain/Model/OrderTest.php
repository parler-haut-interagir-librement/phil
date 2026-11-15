<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\Model;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Exception\LineNotInOrder;
use Phil\CommerceBundle\Domain\Exception\OrderLocked;
use Phil\CommerceBundle\Domain\Exception\PaymentRefused;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Exception\CurrencyMismatch;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Order::class)]
#[CoversClass(OrderLine::class)]
#[CoversClass(LineNotInOrder::class)]
final class OrderTest extends TestCase
{
    public function testAnOpenedOrderIsEmpty(): void
    {
        $order = Order::open('2026-0001', Currency::of('CAD'));

        self::assertSame('2026-0001', $order->number);
        self::assertSame('CAD', $order->currency->code);
        self::assertTrue($order->isEmpty());
        self::assertSame([], $order->lines());
        self::assertSame([], $order->adjustments());
        self::assertNull($order->taxZone());
        self::assertTrue($order->total()->isZero());
        self::assertSame('CAD', $order->total()->currency->code);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNumbers(): iterable
    {
        yield 'empty' => [''];
        yield 'with a space' => ['2026 0001'];
        yield 'too long' => [str_repeat('1', 65)];
        yield 'trailing newline' => ["1\n"];
    }

    public function testAPlacedOrderCountsItsPaymentStarts(): void
    {
        $order = Order::open('1', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'dice'), 'Dice', Money::of(1_000, Currency::of('CAD')), 'goods'), 1, Money::of(1_000, Currency::of('CAD')));

        try {
            $order->countPaymentStart();
            self::fail('A cart counted a payment.');
        } catch (PaymentRefused $paymentRefused) {
            self::assertSame('The order 1 is not placed: only a placed order is paid.', $paymentRefused->getMessage());
        }

        $order->place(new DateTimeImmutable());
        $order->countPaymentStart();
        $order->countPaymentStart();

        self::assertSame(2, $order->paymentsStarted());
    }

    #[DataProvider('invalidNumbers')]
    public function testAnInvalidNumberIsRefused(string $number): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not an order number: 1 to 64 visible ASCII characters.', $number));

        Order::open($number, Currency::of('CAD'));
    }

    public function testTheLongestNumberIsAccepted(): void
    {
        self::assertSame(str_repeat('1', 64), Order::open(str_repeat('1', 64), Currency::of('CAD'))->number);
    }

    public function testLinesAreKeptInTheOrderTheyWereAdded(): void
    {
        $order = $this->order();
        $order->add($this->sellable('42', 1_000), 2, $this->cad(900));
        $order->add($this->sellable('7', 500), 1, $this->cad(500));

        self::assertFalse($order->isEmpty());
        self::assertSame(['product:42', 'product:7'], array_map(static fn (OrderLine $line): string => $line->reference->value(), $order->lines()));

        $line = $order->line(SellableReference::of('product', '42'));
        self::assertNotNull($line);
        self::assertSame('Item 42', $line->description);
        self::assertSame(2, $line->quantity);
        self::assertSame(900, $line->unitPrice->minorUnits);
        self::assertSame('goods', $line->taxCategory);
        self::assertSame(1_800, $line->subtotal()->minorUnits);
        self::assertSame(2_300, $order->itemsTotal()->minorUnits);
        self::assertSame(2_300, $order->total()->minorUnits);
        self::assertNull($order->line(SellableReference::of('product', '8')));
    }

    /**
     * Adding what a line already sells adds to its quantity, at the unit price given last.
     */
    public function testAddingTheSameSellableAddsToItsLine(): void
    {
        $order = $this->order();
        $order->add($this->sellable('42', 1_000), 2, $this->cad(1_000));
        $order->add($this->sellable('42', 1_000), 3, $this->cad(800));

        self::assertCount(1, $order->lines());
        self::assertSame(5, $order->lines()[0]->quantity);
        self::assertSame(4_000, $order->itemsTotal()->minorUnits);
    }

    public function testAQuantityIsChangedAtANewUnitPrice(): void
    {
        $order = $this->order();
        $order->add($this->sellable('42', 1_000), 2, $this->cad(1_000));
        $order->changeQuantity(SellableReference::of('product', '42'), 10, $this->cad(750));

        self::assertSame(10, $order->lines()[0]->quantity);
        self::assertSame(7_500, $order->total()->minorUnits);
    }

    public function testALineHoldsAtLeastOneUnit(): void
    {
        $order = $this->order();
        $order->add($this->sellable('42', 1_000), 1, $this->cad(1_000));

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A line holds at least one unit; 0 is not. Remove the line instead.');

        $order->changeQuantity(SellableReference::of('product', '42'), 0, $this->cad(1_000));
    }

    public function testANewLineHoldsAtLeastOneUnit(): void
    {
        $this->expectException(InvalidValue::class);

        $this->order()->add($this->sellable('42', 1_000), 0, $this->cad(1_000));
    }

    public function testAChangedUnitPriceIsNeverNegative(): void
    {
        $order = $this->order();
        $order->add($this->sellable('42', 1_000), 1, $this->cad(1_000));

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The line product:42 has a negative unit price; a discount is an adjustment.');

        $order->changeQuantity(SellableReference::of('product', '42'), 1, $this->cad(-1));
    }

    public function testANewUnitPriceIsNeverNegative(): void
    {
        $this->expectException(InvalidValue::class);

        $this->order()->add($this->sellable('42', 1_000), 1, $this->cad(-1));
    }

    public function testAPriceInAnotherCurrencyIsRefused(): void
    {
        $this->expectException(CurrencyMismatch::class);
        $this->expectExceptionMessageIsOrContains('An amount in CAD cannot be combined with an amount in USD.');

        $this->order()->add($this->sellable('42', 1_000), 1, Money::of(1_000, Currency::of('USD')));
    }

    public function testAChangedPriceInAnotherCurrencyIsRefused(): void
    {
        $order = $this->order();
        $order->add($this->sellable('42', 1_000), 1, $this->cad(1_000));

        $this->expectException(CurrencyMismatch::class);

        $order->changeQuantity(SellableReference::of('product', '42'), 2, Money::of(1_000, Currency::of('USD')));
    }

    public function testChangingALineTheOrderDoesNotHaveIsRefused(): void
    {
        $this->expectException(LineNotInOrder::class);
        $this->expectExceptionMessageIsOrContains('The order 2026-0001 has no line product:42.');

        $this->order()->changeQuantity(SellableReference::of('product', '42'), 1, $this->cad(100));
    }

    public function testRemovingALineTheOrderDoesNotHaveIsRefused(): void
    {
        $this->expectException(LineNotInOrder::class);

        $this->order()->remove(SellableReference::of('product', '42'));
    }

    public function testRemovingALineRemovesItsAdjustmentsOnly(): void
    {
        $order = $this->order();
        $order->add($this->sellable('42', 1_000), 1, $this->cad(1_000));
        $order->add($this->sellable('7', 500), 1, $this->cad(500));
        $order->replaceAdjustments(AdjustmentType::Tax, [
            Adjustment::of(AdjustmentType::Tax, 'GST', $this->cad(50), SellableReference::of('product', '42')),
            Adjustment::of(AdjustmentType::Tax, 'GST', $this->cad(25), SellableReference::of('product', '7')),
        ]);
        $order->replaceAdjustments(AdjustmentType::Fee, [Adjustment::of(AdjustmentType::Fee, 'Handling', $this->cad(200))]);

        $order->remove(SellableReference::of('product', '42'));

        self::assertSame(['product:7'], array_map(static fn (OrderLine $line): string => $line->reference->value(), $order->lines()));
        self::assertSame([25, 200], array_map(static fn (Adjustment $adjustment): int => $adjustment->amount->minorUnits, $order->adjustments()));
        self::assertSame(725, $order->total()->minorUnits);
    }

    public function testATaxZoneIsSetAndCleared(): void
    {
        $order = $this->order();
        $order->changeTaxZone('QC');
        self::assertSame('QC', $order->taxZone());

        $order->changeTaxZone(null);
        self::assertNull($order->taxZone());
    }

    /**
     * The taxes the prices include are the neutral tax adjustments only: an added tax, or a neutral adjustment of
     * another type, is not counted.
     */
    public function testTaxesAreIncludedInThePricesOrAdded(): void
    {
        $order = $this->order();
        self::assertFalse($order->taxIncluded());

        $order->changeTaxIncluded(true);
        self::assertTrue($order->taxIncluded());
        $cad = Currency::of('CAD');
        $order->replaceAdjustments(AdjustmentType::Tax, [
            Adjustment::of(AdjustmentType::Tax, 'GST', Money::of(44, $cad), null, true),
            Adjustment::of(AdjustmentType::Tax, 'QST', Money::of(87, $cad), null, true),
            Adjustment::of(AdjustmentType::Tax, 'HST', Money::of(5, $cad)),
        ]);
        $order->replaceAdjustments(AdjustmentType::Fee, [Adjustment::of(AdjustmentType::Fee, 'Deposit', Money::of(300, $cad), null, true)]);
        self::assertSame(131, $order->includedTax()->minorUnits);
        self::assertSame('CAD', $order->includedTax()->currency->code);

        $order->changeTaxIncluded(false);
        self::assertFalse($order->taxIncluded());
        self::assertSame(0, Order::open('2', Currency::of('EUR'))->includedTax()->minorUnits);
        self::assertSame('EUR', Order::open('2', Currency::of('EUR'))->includedTax()->currency->code);

        $order->add($this->sellable('42', 1_000), 1, $this->cad(1_000));
        $order->place(new DateTimeImmutable('2026-11-04T12:00:00Z'));
        $this->expectException(OrderLocked::class);
        $order->changeTaxIncluded(true);
    }

    public function testTheChannelAndCustomerGroupAreSetAndCleared(): void
    {
        $order = $this->order();
        self::assertNull($order->channel());
        self::assertNull($order->customerGroup());

        $order->changeChannel('store-montreal');
        $order->changeCustomerGroup(str_repeat('g', 64));
        self::assertSame('store-montreal', $order->channel());
        self::assertSame(str_repeat('g', 64), $order->customerGroup());

        $order->changeChannel(null);
        $order->changeCustomerGroup(null);
        self::assertNull($order->channel());
        self::assertNull($order->customerGroup());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'with a space' => ['my store'];
        yield 'too long' => [str_repeat('g', 65)];
        yield 'trailing newline' => ["web\n"];
    }

    public function testADeliveryMethodIsChosenAndCleared(): void
    {
        $order = $this->order();
        self::assertNull($order->deliveryMethod());

        $order->chooseDeliveryMethod('pickup-montreal');
        self::assertSame('pickup-montreal', $order->deliveryMethod());

        $order->chooseDeliveryMethod(null);
        self::assertNull($order->deliveryMethod());
    }

    #[DataProvider('invalidNames')]
    public function testAnInvalidDeliveryMethodIsRefused(string $name): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a delivery method: 1 to 64 visible ASCII characters.', $name));

        $this->order()->chooseDeliveryMethod($name);
    }

    #[DataProvider('invalidNames')]
    public function testAnInvalidChannelIsRefused(string $name): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a channel: 1 to 64 visible ASCII characters.', $name));

        $this->order()->changeChannel($name);
    }

    #[DataProvider('invalidNames')]
    public function testAnInvalidCustomerGroupIsRefused(string $name): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a customer group: 1 to 64 visible ASCII characters.', $name));

        $this->order()->changeCustomerGroup($name);
    }

    public function testCodesAreEnteredOnceAndRemoved(): void
    {
        $order = $this->order();
        $order->enterCode('SAVE10');
        $order->enterCode('FREESHIP');
        $order->enterCode('SAVE10');
        $order->enterCode(str_repeat('C', 64));

        self::assertSame(['SAVE10', 'FREESHIP', str_repeat('C', 64)], $order->codes());

        $order->removeCode('SAVE10');
        $order->removeCode('UNKNOWN');
        self::assertSame(['FREESHIP', str_repeat('C', 64)], $order->codes());
    }

    #[DataProvider('invalidNames')]
    public function testAnInvalidCodeIsRefused(string $code): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a promotion code: 1 to 64 visible ASCII characters.', $code));

        $this->order()->enterCode($code);
    }

    public function testAnEmptyTaxZoneIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A tax zone, when given, has a name.');

        $this->order()->changeTaxZone('');
    }

    /**
     * A processor replaces its own type and nothing else; neutral adjustments are shown but not added.
     */
    public function testAdjustmentsAreReplacedByTypeAndTotalledUnlessNeutral(): void
    {
        $order = $this->order();
        $line = SellableReference::of('product', '42');
        $order->add($this->sellable('42', 1_000), 2, $this->cad(1_000));
        $order->replaceAdjustments(AdjustmentType::Promotion, [Adjustment::of(AdjustmentType::Promotion, 'Sale', $this->cad(-300), $line)]);
        $order->replaceAdjustments(AdjustmentType::Tax, [
            Adjustment::of(AdjustmentType::Tax, 'GST', $this->cad(85), $line),
            Adjustment::of(AdjustmentType::Tax, 'VAT', $this->cad(400), $line, neutral: true),
        ]);
        $order->replaceAdjustments(AdjustmentType::Shipping, [Adjustment::of(AdjustmentType::Shipping, 'Post', $this->cad(1_200))]);

        self::assertSame(2_000, $order->itemsTotal()->minorUnits);
        self::assertSame(-300, $order->adjustmentsTotal(AdjustmentType::Promotion)->minorUnits);
        self::assertSame(85, $order->adjustmentsTotal(AdjustmentType::Tax)->minorUnits);
        self::assertSame(985, $order->adjustmentsTotal()->minorUnits);
        self::assertSame(2_985, $order->total()->minorUnits);
        self::assertCount(4, $order->adjustments());
        self::assertSame(['GST', 'VAT'], array_map(static fn (Adjustment $adjustment): string => $adjustment->label, $order->adjustments(AdjustmentType::Tax)));
        self::assertSame(['Sale', 'GST', 'VAT'], array_map(static fn (Adjustment $adjustment): string => $adjustment->label, $order->lineAdjustments($line)));
        self::assertSame(['Sale'], array_map(static fn (Adjustment $adjustment): string => $adjustment->label, $order->lineAdjustments($line, AdjustmentType::Promotion)));
        self::assertSame([], $order->lineAdjustments(SellableReference::of('product', '7')));

        $order->replaceAdjustments(AdjustmentType::Tax, [Adjustment::of(AdjustmentType::Tax, 'HST', $this->cad(221), $line)]);

        self::assertSame(['Sale', 'Post', 'HST'], array_map(static fn (Adjustment $adjustment): string => $adjustment->label, $order->adjustments()));
        self::assertSame(3_121, $order->total()->minorUnits);

        $order->replaceAdjustments(AdjustmentType::Tax, []);
        self::assertSame([], $order->adjustments(AdjustmentType::Tax));
    }

    public function testAdjustmentsAreGroupedByLineInOnePass(): void
    {
        $order = $this->order();
        $first = SellableReference::of('product', '42');
        $second = SellableReference::of('product', '43');
        $order->add($this->sellable('42', 1_000), 1, $this->cad(1_000));
        $order->add($this->sellable('43', 500), 1, $this->cad(500));
        $order->replaceAdjustments(AdjustmentType::Promotion, [Adjustment::of(AdjustmentType::Promotion, 'Sale', $this->cad(-300), $second)]);
        $order->replaceAdjustments(AdjustmentType::Tax, [
            Adjustment::of(AdjustmentType::Tax, 'GST', $this->cad(50), $first),
            Adjustment::of(AdjustmentType::Tax, 'GST', $this->cad(10), $second),
            Adjustment::of(AdjustmentType::Tax, 'QST', $this->cad(20), $second),
        ]);
        $order->replaceAdjustments(AdjustmentType::Shipping, [Adjustment::of(AdjustmentType::Shipping, 'Post', $this->cad(1_200))]);

        self::assertSame(['product:43' => ['Sale -300', 'GST 10', 'QST 20'], 'product:42' => ['GST 50']], $this->labels($order->adjustmentsByLine()));
        self::assertSame(['product:43' => ['Sale -300']], $this->labels($order->adjustmentsByLine(AdjustmentType::Promotion)));
        self::assertSame([], $order->adjustmentsByLine(AdjustmentType::Fee));
    }

    public function testAnAdjustmentOfAnotherTypeCannotReplaceAType(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A fee adjustment cannot replace the tax adjustments.');

        $this->order()->replaceAdjustments(AdjustmentType::Tax, [Adjustment::of(AdjustmentType::Fee, 'Fee', $this->cad(1))]);
    }

    public function testAnAdjustmentInAnotherCurrencyIsRefused(): void
    {
        $this->expectException(CurrencyMismatch::class);

        $this->order()->replaceAdjustments(AdjustmentType::Fee, [Adjustment::of(AdjustmentType::Fee, 'Fee', Money::of(1, Currency::of('EUR')))]);
    }

    public function testAnAdjustmentOnALineTheOrderDoesNotHaveIsRefused(): void
    {
        $this->expectException(LineNotInOrder::class);
        $this->expectExceptionMessageIsOrContains('The order 2026-0001 has no line product:9.');

        $this->order()->replaceAdjustments(AdjustmentType::Fee, [Adjustment::of(AdjustmentType::Fee, 'Fee', $this->cad(1), SellableReference::of('product', '9'))]);
    }

    /**
     * A refused replacement leaves the adjustments as they were.
     */
    public function testARefusedReplacementChangesNothing(): void
    {
        $order = $this->order();
        $order->replaceAdjustments(AdjustmentType::Fee, [Adjustment::of(AdjustmentType::Fee, 'Fee', $this->cad(100))]);

        try {
            $order->replaceAdjustments(AdjustmentType::Fee, [
                Adjustment::of(AdjustmentType::Fee, 'Other', $this->cad(5)),
                Adjustment::of(AdjustmentType::Fee, 'Wrong', $this->cad(5), SellableReference::of('product', '9')),
            ]);
            self::fail('The replacement was accepted.');
        } catch (LineNotInOrder) {
        }

        self::assertSame(['Fee'], array_map(static fn (Adjustment $adjustment): string => $adjustment->label, $order->adjustments()));
    }

    public function testItsErrorsAreDomainErrors(): void
    {
        self::assertInstanceOf(DomainError::class, LineNotInOrder::of('1', SellableReference::of('product', '1')));
    }

    private function order(): Order
    {
        return Order::open('2026-0001', Currency::of('CAD'));
    }

    private function sellable(string $id, int $price): Sellable
    {
        return Sellable::of(SellableReference::of('product', $id), 'Item '.$id, $this->cad($price), 'goods');
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }

    /**
     * @param array<string, list<Adjustment>> $byLine
     *
     * @return array<string, list<string>>
     */
    private function labels(array $byLine): array
    {
        return array_map(static fn (array $adjustments): array => array_map(static fn (Adjustment $adjustment): string => $adjustment->label.' '.$adjustment->amount->minorUnits, $adjustments), $byLine);
    }
}
