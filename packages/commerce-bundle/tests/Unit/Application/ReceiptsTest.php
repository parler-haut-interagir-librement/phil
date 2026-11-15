<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\Port\DocumentNumbering;
use Phil\CommerceBundle\Application\Receipts;
use Phil\CommerceBundle\Domain\Exception\ReceiptRefused;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Receipt\DocumentKind;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Tests\Unit\Support\CountingNumbering;
use Phil\CommerceBundle\Tests\Unit\Support\InMemoryReceipts;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Receipts::class)]
final class ReceiptsTest extends TestCase
{
    private InMemoryReceipts $repository;

    private CountingNumbering $numbering;

    private Receipts $receipts;

    protected function setUp(): void
    {
        $this->repository = new InMemoryReceipts();
        $this->numbering = new CountingNumbering();
        $this->receipts = new Receipts($this->numbering, $this->repository, ['name' => 'Le Magasin', 'address' => ['Québec'], 'tax_numbers' => ['GST' => '123']]);
    }

    public function testAReceiptIsNumberedInItsSeriesAndKept(): void
    {
        $at = new DateTimeImmutable('2026-11-03T16:00:00Z');
        $receipt = $this->receipts->issue($this->order('1'), $at);

        self::assertSame('receipt-1', $receipt->number);
        self::assertSame($at, $receipt->issuedAt);
        self::assertSame(['name' => 'Le Magasin', 'address' => ['Québec'], 'tax_numbers' => ['GST' => '123']], $receipt->issuer->toArray());
        self::assertSame([$receipt], $this->repository->receipts);
        self::assertSame('receipt-2', $this->receipts->issue($this->order('2'), $at)->number);
    }

    public function testAnOrderGetsOneReceipt(): void
    {
        $order = $this->order('1');
        $this->receipts->issue($order, new DateTimeImmutable());

        try {
            $this->receipts->issue($order, new DateTimeImmutable());
            self::fail('A second receipt was issued.');
        } catch (ReceiptRefused $receiptRefused) {
            self::assertSame('The order 1 already has the receipt receipt-1.', $receiptRefused->getMessage());
        }

        self::assertSame(['receipt' => 1], $this->numbering->last);
    }

    /**
     * A refused receipt takes no number: the series has no gap.
     */
    public function testACartTakesNoNumber(): void
    {
        try {
            $this->receipts->issue(Order::open('9', Currency::of('CAD')), new DateTimeImmutable());
            self::fail('A cart got a receipt.');
        } catch (ReceiptRefused $receiptRefused) {
            self::assertSame('The order 9 is not placed: only a placed order gets a receipt.', $receiptRefused->getMessage());
        }

        self::assertSame([], $this->numbering->last);
        self::assertSame([], $this->repository->receipts);
    }

    public function testCreditNotesAreNumberedInTheirOwnSeries(): void
    {
        $receipt = $this->receipts->issue($this->order('1'), new DateTimeImmutable());

        $first = $this->receipts->credit($receipt, new DateTimeImmutable(), [SellableReference::of('product', 'a')]);
        $second = $this->receipts->credit($receipt, new DateTimeImmutable('2026-11-10T00:00:00Z'));

        self::assertSame('credit_note-1', $first->number);
        self::assertSame('credit_note-2', $second->number);
        self::assertSame(DocumentKind::CreditNote, $second->kind);
        self::assertSame('2026-11-10T00:00:00+00:00', $second->issuedAt->format(\DATE_ATOM));
        self::assertSame([$receipt, $first, $second], $this->repository->receipts);
        self::assertSame(-1_000, $second->total()->minorUnits);
    }

    /**
     * A refused credit note takes no number either.
     */
    public function testARefusedCreditNoteTakesNoNumber(): void
    {
        $receipt = $this->receipts->issue($this->order('1'), new DateTimeImmutable());
        $this->receipts->credit($receipt, new DateTimeImmutable());

        try {
            $this->receipts->credit($receipt, new DateTimeImmutable());
            self::fail('A second full note was issued.');
        } catch (ReceiptRefused) {
        }

        self::assertSame(['receipt' => 1, 'credit_note' => 1], $this->numbering->last);
        self::assertCount(2, $this->repository->receipts);
    }

    /**
     * Another transaction issued the order's receipt while this one waited on the series' lock: it is seen once
     * the number is taken, and the issue is refused, its transaction to roll back with the number.
     */
    public function testAReceiptIssuedWhileWaitingForTheNumberIsSeen(): void
    {
        $order = $this->order('1');
        $other = new Receipts(new CountingNumbering(), $this->repository, ['name' => 'Le Magasin', 'address' => [], 'tax_numbers' => []]);
        $receipts = new Receipts($this->racingNumbering(static fn (): mixed => $other->issue($order, new DateTimeImmutable())), $this->repository, ['name' => 'Le Magasin', 'address' => [], 'tax_numbers' => []]);

        try {
            $receipts->issue($order, new DateTimeImmutable());
            self::fail('A second receipt was issued.');
        } catch (ReceiptRefused $receiptRefused) {
            self::assertSame('The order 1 already has the receipt receipt-1.', $receiptRefused->getMessage());
        }

        self::assertCount(1, $this->repository->receipts);
    }

    /**
     * Another transaction credited the receipt in full while this one waited on the series' lock: the lines are
     * counted again once the number is taken, and the second full note is refused.
     */
    public function testACreditNoteIssuedWhileWaitingForTheNumberIsCounted(): void
    {
        $receipt = $this->receipts->issue($this->order('1'), new DateTimeImmutable());
        $other = new Receipts(new CountingNumbering(), $this->repository, ['name' => 'Le Magasin', 'address' => [], 'tax_numbers' => []]);
        $receipts = new Receipts($this->racingNumbering(static fn (): mixed => $other->credit($receipt, new DateTimeImmutable())), $this->repository, ['name' => 'Le Magasin', 'address' => [], 'tax_numbers' => []]);

        try {
            $receipts->credit($receipt, new DateTimeImmutable());
            self::fail('The receipt was credited twice.');
        } catch (ReceiptRefused) {
        }

        self::assertCount(2, $this->repository->receipts);
    }

    public function testAnInvalidIssuerFailsAtOnce(): void
    {
        $this->expectException(InvalidValue::class);

        new Receipts($this->numbering, $this->repository, ['name' => ' ', 'address' => [], 'tax_numbers' => []]);
    }

    /**
     * A numbering that lets another transaction commit while the number is being taken.
     *
     * @param callable(): mixed $meanwhile
     */
    private function racingNumbering(callable $meanwhile): DocumentNumbering
    {
        return new class($meanwhile) implements DocumentNumbering {
            /** @var callable(): mixed */
            private $meanwhile;

            /**
             * @param callable(): mixed $meanwhile
             */
            public function __construct(callable $meanwhile)
            {
                $this->meanwhile = $meanwhile;
            }

            public function next(DocumentKind $kind): string
            {
                ($this->meanwhile)();

                return 'raced-'.$kind->value;
            }
        };
    }

    private function order(string $number): Order
    {
        $cad = static fn (int $minorUnits): Money => Money::of($minorUnits, Currency::of('CAD'));
        $order = Order::open($number, Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'a'), 'A', $cad(1_000), 'goods'), 1, $cad(1_000));
        $order->add(Sellable::of(SellableReference::of('product', 'b'), 'B', $cad(1_000), 'goods'), 1, $cad(1_000));
        $order->place(new DateTimeImmutable());

        return $order;
    }
}
