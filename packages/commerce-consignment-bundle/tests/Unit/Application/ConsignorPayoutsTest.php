<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Application;

use Phil\CommerceConsignmentBundle\Application\ConsignorPayouts;
use Phil\CommerceConsignmentBundle\Domain\Consignor\PayoutState;
use Phil\CommerceConsignmentBundle\Domain\Exception\ConsignmentRefused;
use Phil\CommerceConsignmentBundle\Domain\Exception\InvalidValue;
use Phil\CommerceConsignmentBundle\Domain\Service\Ledger;
use Phil\CommerceConsignmentBundle\Domain\Stock\IssueOrder;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ConsignmentTerms;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Receipt;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ReceiptLine;
use Phil\CommerceConsignmentBundle\Infrastructure\Memory\InMemoryConsignorRepository;
use Phil\CommerceConsignmentBundle\Infrastructure\Memory\InMemoryLedgerRepository;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\Fixtures;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConsignorPayouts::class)]
#[CoversClass(ConsignmentRefused::class)]
final class ConsignorPayoutsTest extends TestCase
{
    private Ledger $ledger;

    private InMemoryConsignorRepository $consignors;

    private ConsignorPayouts $payouts;

    private Owner $author;

    protected function setUp(): void
    {
        $repository = new InMemoryLedgerRepository();
        $this->ledger = new Ledger($repository);
        $this->consignors = new InMemoryConsignorRepository();
        $this->payouts = new ConsignorPayouts($repository, $this->consignors);
        $this->author = Owner::consignor(Fixtures::AUTHOR);

        // The author is owed 3 × 15.00, less 15.00 for a copy brought back: 30.00.
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(9));
        $this->ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 3, Fixtures::cad(2000), IssueOrder::ConsignedFirst, Fixtures::at(10));
        $this->ledger->returnSold('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::at(11));
    }

    public function testPayoutsLowerTheBalanceUntilItIsPaid(): void
    {
        $cad = Currency::of('CAD');
        $balance = $this->payouts->balance($this->author, $cad);
        self::assertSame([3000, 0, 3000, PayoutState::Due], [$balance->owed->minorUnits, $balance->paid->minorUnits, $balance->balance->minorUnits, $balance->state]);

        $first = $this->payouts->record('PAY-1', $this->author, Fixtures::cad(1000), Fixtures::at(12), 'cheque', '000123', Fixtures::at(13));
        self::assertSame($first, $this->consignors->payoutByKey('PAY-1'));
        $balance = $this->payouts->balance($this->author, $cad);
        self::assertSame([1000, 2000, PayoutState::PartlyPaid], [$balance->paid->minorUnits, $balance->balance->minorUnits, $balance->state]);

        $this->payouts->record('PAY-2', $this->author, Fixtures::cad(2000), Fixtures::at(12), 'transfer', null, Fixtures::at(13));
        self::assertSame(PayoutState::Paid, $this->payouts->balance($this->author, $cad)->state);
        self::assertTrue($this->payouts->balance($this->author, $cad)->balance->isZero());
        self::assertSame(PayoutState::Paid, $this->payouts->balance(Owner::consignor('nobody'), $cad)->state);
        self::assertTrue($this->payouts->balance($this->author, Currency::of('USD'))->owed->isZero());
    }

    public function testThePayoutOfOneKeyIsWrittenOnce(): void
    {
        $this->payouts->record('PAY-1', $this->author, Fixtures::cad(1000), Fixtures::at(12), 'cash', null, Fixtures::at(13));

        try {
            $this->payouts->record('PAY-1', $this->author, Fixtures::cad(1000), Fixtures::at(12), 'cash', null, Fixtures::at(13));
            self::fail('A payout was written twice.');
        } catch (ConsignmentRefused $consignmentRefused) {
            self::assertSame('A payout with the key PAY-1 is written already: the same payout is never written twice.', $consignmentRefused->getMessage());
        }

        self::assertSame(1000, $this->payouts->balance($this->author, Currency::of('CAD'))->paid->minorUnits);
    }

    public function testAPayoutIsAtMostTheBalance(): void
    {
        self::assertSame(3000, $this->payouts->record('PAY-1', $this->author, Fixtures::cad(3000), Fixtures::at(12), 'cash', null, Fixtures::at(13))->amount()->minorUnits);

        $this->expectException(ConsignmentRefused::class);
        $this->expectExceptionMessageIsOrContains('A payout of 0.01 to author-ann is more than the 0.00 the shop owes them.');
        $this->payouts->record('PAY-2', $this->author, Fixtures::cad(1), Fixtures::at(12), 'cash', null, Fixtures::at(13));
    }

    public function testAnInvalidPayoutIsRefusedBeforeItsKeyIsRead(): void
    {
        $this->expectException(InvalidValue::class);
        $this->payouts->record('PAY-1', $this->author, Fixtures::cad(0), Fixtures::at(12), 'cash', null, Fixtures::at(13));
    }

    public function testAReversalCancelsAPayoutOnce(): void
    {
        $payout = $this->payouts->record('PAY-1', $this->author, Fixtures::cad(3000), Fixtures::at(12), 'transfer', 'TRX-1', Fixtures::at(13));
        $reversal = $this->payouts->reverse($payout->id, 'PAY-1-R', Fixtures::at(14));

        self::assertSame(-3000, $reversal->amount()->minorUnits);
        self::assertSame($reversal, $this->consignors->reversalOf($payout->id));
        $balance = $this->payouts->balance($this->author, Currency::of('CAD'));
        self::assertSame([0, 3000, PayoutState::Due], [$balance->paid->minorUnits, $balance->balance->minorUnits, $balance->state]);
        self::assertCount(2, $this->consignors->payoutsOf(Fixtures::AUTHOR, 'CAD', 500));

        foreach ([
            \sprintf('The payout %s is reversed already.', $payout->id) => fn (): Money => $this->payouts->reverse($payout->id, 'PAY-1-R2', Fixtures::at(15))->amount(),
            'No payout missing is written.' => fn (): Money => $this->payouts->reverse('missing', 'PAY-X', Fixtures::at(15))->amount(),
        ] as $message => $reverse) {
            try {
                $reverse();
                self::fail($message);
            } catch (ConsignmentRefused $refused) {
                self::assertSame($message, $refused->getMessage());
            }
        }

        $again = $this->payouts->record('PAY-2', $this->author, Fixtures::cad(3000), Fixtures::at(12), 'transfer', 'TRX-1', Fixtures::at(16));

        $this->expectExceptionMessageIsOrContains('A payout with the key PAY-1-R is written already');
        $this->payouts->reverse($again->id, 'PAY-1-R', Fixtures::at(17));
    }

    public function testAReversalIsNeverReversed(): void
    {
        $payout = $this->payouts->record('PAY-1', $this->author, Fixtures::cad(3000), Fixtures::at(12), 'transfer', null, Fixtures::at(13));
        $reversal = $this->payouts->reverse($payout->id, 'PAY-1-R', Fixtures::at(14));

        $this->expectException(InvalidValue::class);
        $this->payouts->reverse($reversal->id, 'PAY-1-R-R', Fixtures::at(15));
    }

    public function testBalancesAreKeptPerCurrencyAndForConsignorsOnly(): void
    {
        $this->ledger->receive(Receipt::of('CS-9', Fixtures::SHELF, [ReceiptLine::consigned('PEN', 2, Fixtures::AUTHOR, ConsignmentTerms::commission(400_000))]), Fixtures::at(9));
        $this->ledger->sell('ORD-2', 'PEN', Fixtures::SHELF, 2, Money::of(1001, Currency::of('USD')), IssueOrder::OldestFirst, Fixtures::at(10));

        self::assertSame(1201, $this->payouts->balance($this->author, Currency::of('USD'))->owed->minorUnits);
        self::assertSame(3000, $this->payouts->balance($this->author, Currency::of('CAD'))->owed->minorUnits);

        $this->expectExceptionMessageIsOrContains("A balance concerns a consignor's units, not the shop's.");
        $this->payouts->balance(Owner::shop(), Currency::of('CAD'));
    }
}
