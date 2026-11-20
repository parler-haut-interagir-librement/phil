<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Receipt;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\Issuer;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceReturnsBundle\Application\ReturnCreditNotes;
use Phil\CommerceReturnsBundle\Application\Returns;
use Phil\CommerceReturnsBundle\Application\ReturnsGateway;
use Phil\CommerceReturnsBundle\Domain\Event\ReturnCreditNoteIssued;
use Phil\CommerceReturnsBundle\Domain\Exception\ReturnRefused;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnCreditNote;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnRequest;
use Phil\CommerceReturnsBundle\Domain\Rma\ItemCondition;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnReason;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnLine;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnPolicy;
use Phil\CommerceReturnsBundle\Tests\Unit\Support\Documents;
use Phil\CommerceReturnsBundle\Tests\Unit\Support\Events;
use Phil\CommerceReturnsBundle\Tests\Unit\Support\InMemoryOrders;
use Phil\CommerceReturnsBundle\Tests\Unit\Support\InMemoryPayments;
use Phil\CommerceReturnsBundle\Tests\Unit\Support\InMemoryReturns;
use Phil\CommerceReturnsBundle\Tests\Unit\Support\Specials;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReturnCreditNotes::class)]
#[CoversClass(ReturnCreditNote::class)]
final class ReturnCreditNotesTest extends TestCase
{
    private InMemoryOrders $orders;

    private InMemoryReturns $returns;

    private Documents $documents;

    private Events $events;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->orders = new InMemoryOrders();
        $this->returns = new InMemoryReturns();
        $this->documents = new Documents();
        $this->events = new Events();
        $this->at = new DateTimeImmutable('2026-11-03 10:00:00');
    }

    /**
     * Two teas of three, and both products of a special, the mug missing: each line its share with its tax apart, the
     * fee off the tea, the special's whole discount off; the missing mug is not credited, nor the delivery, the tea
     * being no fault of the shop.
     */
    public function testAPartialCreditNoteSaysEverythingTheReturnGivesBack(): void
    {
        $return = $this->received([['product:TEA', 2, ReturnReason::ChangedMind], ['product:BOOK', 1, ReturnReason::Defective], ['product:MUG', 1, ReturnReason::Defective]], ['product:TEA' => ItemCondition::Resellable, 'product:BOOK' => ItemCondition::Damaged, 'product:MUG' => ItemCondition::Missing]);

        $note = $this->notes()->issue($return->id, $this->at);

        self::assertSame(['CREDIT_NOTE-1', $return->id, 'A-1', 'RECEIPT-1', $this->at, 'CAD'], [$note->number, $note->returnId, $note->orderNumber, $note->receiptNumber, $note->issuedAt, $note->currency->code]);
        self::assertSame([
            ['product:TEA', 'Tea', 2, 3, 1_150, 150, 115],
            ['product:BOOK', 'Book', 1, 1, 2_500 + 375 + 575, 375, 0],
        ], array_map(static fn (array $line): array => [$line['line'], $line['description'], $line['quantity'], $line['line_quantity'], $line['amount']->minorUnits, $line['taxes']->minorUnits, $line['fee']->minorUnits], $note->lines()));
        self::assertSame([575 + 575, 0], [$note->specialDiscount()->minorUnits, $note->delivery()->minorUnits]);
        self::assertSame($return->value()->minorUnits, $note->total()->minorUnits);
        self::assertSame(1_150 - 115 + 3_450 - 1_150, $note->total()->minorUnits);
        self::assertSame($note, $this->returns->findCreditNote($return->id));

        $issued = $this->events->dispatched[0];
        self::assertInstanceOf(ReturnCreditNoteIssued::class, $issued);
        self::assertSame(['CREDIT_NOTE-1', $return->id, 'A-1', $note->total()->minorUnits, $this->at], [$issued->number, $issued->returnId, $issued->orderNumber, $issued->total->minorUnits, $issued->occurredAt()]);
    }

    public function testAReturnIsCreditedOnce(): void
    {
        $return = $this->received([['product:TEA', 1, ReturnReason::Defective]], ['product:TEA' => ItemCondition::Resellable]);
        $this->notes()->issue($return->id, $this->at);

        try {
            $this->notes()->issue($return->id, $this->at);
            self::fail('A return was credited twice.');
        } catch (ReturnRefused $returnRefused) {
            self::assertSame(\sprintf('The return %s is already credited by CREDIT_NOTE-1.', $return->id), $returnRefused->getMessage());
            self::assertSame(['credit_note' => 1], $this->documents->sequences, 'A refused note never takes a number.');
        }
    }

    public function testWithoutADispatcherTheNoteIsIssuedAllTheSame(): void
    {
        $return = $this->received([['product:TEA', 1, ReturnReason::Defective]], ['product:TEA' => ItemCondition::Resellable]);

        self::assertSame('CREDIT_NOTE-1', new ReturnCreditNotes($this->returns, $this->documents, $this->documents)->issue($return->id, $this->at)->number);
        self::assertSame([], array_filter($this->events->dispatched, static fn (object $event): bool => $event instanceof ReturnCreditNoteIssued));
    }

    public function testAMissingLineDoesNotStopTheLinesAfterIt(): void
    {
        $return = $this->received([['product:MUG', 1, ReturnReason::Defective], ['product:TEA', 3, ReturnReason::Defective]], ['product:MUG' => ItemCondition::Missing, 'product:TEA' => ItemCondition::Resellable]);

        $lines = $this->notes()->issue($return->id, $this->at)->lines();

        self::assertSame(['product:TEA'], array_column($lines, 'line'));
        self::assertSame(225, $lines[0]['taxes']->minorUnits);
    }

    public function testALineWithoutTaxCarriesNone(): void
    {
        $return = $this->received([['product:TEA', 3, ReturnReason::Defective]], ['product:TEA' => ItemCondition::Resellable], taxed: false);

        self::assertSame(0, $this->notes()->issue($return->id, $this->at)->lines()[0]['taxes']->minorUnits);
    }

    /**
     * What an earlier return took back is not counted twice in the taxes of the next: three units carry 2.25, one
     * 0.75, so the two after it 1.50.
     */
    public function testALaterReturnCarriesItsUnitsShareOfTheTaxes(): void
    {
        $order = $this->order();
        $this->returnsService()->request($order, [['line' => 'product:TEA', 'quantity' => 1, 'reason' => ReturnReason::Defective]], $this->at);
        $second = $this->received([['product:TEA', 2, ReturnReason::Defective]], ['product:TEA' => ItemCondition::Resellable], fresh: false);

        self::assertSame([150], array_map(static fn (array $line): int => $line['taxes']->minorUnits, $this->notes()->issue($second->id, $this->at)->lines()));
    }

    /**
     * What the lines give caps the specials' discount: their amounts less their fees, one after the other.
     */
    public function testTheSpecialsDiscountIsCappedByWhatTheLinesGiveLessTheirFees(): void
    {
        $return = ReturnRequest::request('A-1', Currency::of('CAD'), [ReturnLine::of('product:A', 1, ReturnReason::Other, 200, 1), ReturnLine::of('product:B', 1, ReturnReason::Other, 100, 1)], 0, $this->at, ['promotion:duo' => 500]);
        $lines = [
            ['line' => 'product:A', 'description' => 'A', 'quantity' => 1, 'line_quantity' => 1, 'amount' => 200, 'taxes' => 0, 'fee' => 20],
            ['line' => 'product:B', 'description' => 'B', 'quantity' => 1, 'line_quantity' => 1, 'amount' => 100, 'taxes' => 0, 'fee' => 10],
        ];

        self::assertSame(270, ReturnCreditNote::issue('CN-1', $return, 'R-1', $lines, $this->at)->specialDiscount()->minorUnits);
        self::assertSame(0, ReturnCreditNote::issue('CN-2', $return, 'R-1', [], $this->at)->specialDiscount()->minorUnits);
        $below = [['line' => 'product:A', 'description' => 'A', 'quantity' => 1, 'line_quantity' => 1, 'amount' => -50, 'taxes' => 0, 'fee' => 0]];
        self::assertSame(0, ReturnCreditNote::issue('CN-3', $return, 'R-1', $below, $this->at)->specialDiscount()->minorUnits, 'A note never goes below zero for the specials.');
    }

    /**
     * A note another transaction issued while the number was taken is seen under the series' lock.
     */
    public function testANoteIssuedMeanwhileIsSeenUnderTheLock(): void
    {
        $return = $this->received([['product:TEA', 1, ReturnReason::Defective]], ['product:TEA' => ItemCondition::Resellable]);
        $receipt = $this->documents->receiptOf('A-1');
        self::assertNotNull($receipt);
        $this->documents->meanwhile[] = fn () => $this->documents->add(Receipt::credit('CN-OTHER', $receipt, [], $this->at, [SellableReference::of('product', 'TEA')]));

        try {
            $this->notes()->issue($return->id, $this->at);
            self::fail('A line credited meanwhile was credited again.');
        } catch (ReturnRefused $returnRefused) {
            self::assertSame('The line product:TEA is already credited whole by CN-OTHER.', $returnRefused->getMessage());
            self::assertSame([], $this->returns->creditNotes);
        }
    }

    public function testALineTheCoreCreditedWholeIsNotCreditedAgain(): void
    {
        $return = $this->received([['product:TEA', 1, ReturnReason::Defective]], ['product:TEA' => ItemCondition::Resellable]);
        $receipt = $this->documents->receiptOf('A-1');
        self::assertNotNull($receipt);
        $this->documents->add(Receipt::credit('CN-9', $receipt, [], $this->at, [SellableReference::of('product', 'MUG')]));
        self::assertSame('CREDIT_NOTE-1', $this->notes()->issue($return->id, $this->at)->number, 'Another line credited whole is no obstacle.');

        $second = $this->received([['product:BOOK', 1, ReturnReason::Defective]], ['product:BOOK' => ItemCondition::Resellable], fresh: false);
        $this->documents->add(Receipt::credit('CN-10', $receipt, [], $this->at, [SellableReference::of('product', 'BOOK')]));

        $this->expectException(ReturnRefused::class);
        $this->expectExceptionMessageIsOrContains('The line product:BOOK is already credited whole by CN-10.');

        $this->notes()->issue($second->id, $this->at);
    }

    public function testAReturnNotReceivedWaits(): void
    {
        $return = $this->returnsService()->request($this->order(), [['line' => 'product:TEA', 'quantity' => 1, 'reason' => ReturnReason::Defective]], $this->at);
        $this->returnsService()->authorize($return->id, $this->at);

        try {
            $this->notes()->issue($return->id, $this->at);
            self::fail('An authorized return was credited.');
        } catch (ReturnRefused $returnRefused) {
            self::assertSame(\sprintf('The return %s is authorized: a credit note waits until its goods were received.', $return->id), $returnRefused->getMessage());
            self::assertSame([], $this->documents->sequences);
        }

        $this->returnsService()->receive($return->id, ['product:TEA' => ItemCondition::Resellable], $this->at);
        $this->returnsService()->refund($return->id, $this->at);
        self::assertSame('CREDIT_NOTE-1', $this->notes()->issue($return->id, $this->at)->number, 'A resolved return is credited too.');
    }

    public function testAnOrderWithoutAReceiptHasNothingToCredit(): void
    {
        $return = $this->received([['product:TEA', 1, ReturnReason::Defective]], ['product:TEA' => ItemCondition::Resellable], receipt: false);

        try {
            $this->notes()->issue($return->id, $this->at);
            self::fail('A note credited no receipt.');
        } catch (ReturnRefused $returnRefused) {
            self::assertSame('The order A-1 has no receipt: a credit note reverses one.', $returnRefused->getMessage());
            self::assertSame([], $this->documents->sequences);
        }

        $this->expectException(ReturnRefused::class);
        $this->expectExceptionMessageIsOrContains('No return is known as nope.');

        $this->notes()->issue('nope', $this->at);
    }

    /**
     * The specials' discount never takes a note below zero: what the lines give caps it.
     */
    public function testTheSpecialsDiscountComesOffWhatTheLinesGive(): void
    {
        $return = $this->received([['product:BOOK', 1, ReturnReason::Defective]], ['product:BOOK' => ItemCondition::Missing]);
        $note = $this->notes()->issue($return->id, $this->at);

        self::assertSame([], $note->lines());
        self::assertSame([0, 800, 800], [$note->specialDiscount()->minorUnits, $note->delivery()->minorUnits, $note->total()->minorUnits]);
        self::assertSame($return->value()->minorUnits, $note->total()->minorUnits);
    }

    private function notes(): ReturnCreditNotes
    {
        return new ReturnCreditNotes($this->returns, $this->documents, $this->documents, $this->events);
    }

    private function returnsService(): Returns
    {
        return new Returns($this->orders, $this->returns, new Payments([new ReturnsGateway()], new InMemoryPayments()), ReturnPolicy::of(restockingFee: 100_000), specials: new Specials(['promotion:duo']));
    }

    /**
     * @param list<array{string, int, ReturnReason}> $lines
     * @param array<string, ItemCondition>           $conditions
     */
    private function received(array $lines, array $conditions, bool $receipt = true, bool $fresh = true, bool $taxed = true): ReturnRequest
    {
        $order = $fresh ? $this->order($receipt, $taxed) : $this->orders->find('A-1');
        self::assertInstanceOf(Order::class, $order);
        $return = $this->returnsService()->request($order, array_map(static fn (array $line): array => ['line' => $line[0], 'quantity' => $line[1], 'reason' => $line[2]], $lines), $this->at);
        $this->returnsService()->authorize($return->id, $this->at);

        return $this->returnsService()->receive($return->id, $conditions, $this->at);
    }

    /**
     * Tea: 3 at 5.00, 2.25 of tax; a book at 30.00 and a mug at 20.00 in a special of 10.00 off, spread 5.00 each,
     * taxed 3.75 and 2.25 after it, and an included tax aside; a delivery of 8.00.
     */
    private function order(bool $receipt = true, bool $taxed = true): Order
    {
        $cad = Currency::of('CAD');
        $order = Order::open('A-1', $cad);
        $tea = SellableReference::of('product', 'TEA');
        $book = SellableReference::of('product', 'BOOK');
        $mug = SellableReference::of('product', 'MUG');
        $order->add(Sellable::of($tea, 'Tea', Money::of(500, $cad), 'standard'), 3, Money::of(500, $cad));
        $order->add(Sellable::of($book, 'Book', Money::of(3_000, $cad), 'standard'), 1, Money::of(3_000, $cad));
        $order->add(Sellable::of($mug, 'Mug', Money::of(2_000, $cad), 'standard'), 1, Money::of(2_000, $cad));
        $order->replaceAdjustments(AdjustmentType::Promotion, [
            Adjustment::of(AdjustmentType::Promotion, 'Duo', Money::of(-500, $cad), $book, origin: 'promotion:duo'),
            Adjustment::of(AdjustmentType::Promotion, 'Duo', Money::of(-500, $cad), $mug, origin: 'promotion:duo'),
        ]);
        $order->replaceAdjustments(AdjustmentType::Tax, $taxed ? [
            Adjustment::of(AdjustmentType::Tax, 'Tax', Money::of(100, $cad), $tea),
            Adjustment::of(AdjustmentType::Tax, 'Tax', Money::of(125, $cad), $tea),
            Adjustment::of(AdjustmentType::Tax, 'Tax', Money::of(375, $cad), $book),
            Adjustment::of(AdjustmentType::Tax, 'Tax', Money::of(225, $cad), $mug),
            Adjustment::of(AdjustmentType::Tax, 'Included', Money::of(999, $cad), $tea, true),
        ] : []);
        $order->replaceAdjustments(AdjustmentType::Shipping, [Adjustment::of(AdjustmentType::Shipping, 'Delivery', Money::of(800, $cad))]);
        $order->place($this->at);

        $this->orders->add($order);

        if ($receipt) {
            $this->documents->add(Receipt::issue('RECEIPT-1', $order, Issuer::fromArray(['name' => 'Shop', 'address' => ['1 Main St'], 'tax_numbers' => []]), $this->at));
        }

        return $order;
    }
}
