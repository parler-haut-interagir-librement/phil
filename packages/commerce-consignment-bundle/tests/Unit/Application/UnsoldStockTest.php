<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Application;

use Phil\CommerceConsignmentBundle\Application\ConsignorAgreements;
use Phil\CommerceConsignmentBundle\Application\UnsoldStock;
use Phil\CommerceConsignmentBundle\Domain\Consignor\StatementFrequency;
use Phil\CommerceConsignmentBundle\Domain\Consignor\UnsoldPolicy;
use Phil\CommerceConsignmentBundle\Domain\Exception\ConsignmentRefused;
use Phil\CommerceConsignmentBundle\Domain\Exception\InvalidValue;
use Phil\CommerceConsignmentBundle\Domain\Service\Ledger;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ConsignmentTerms;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\DueUnits;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Receipt;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ReceiptLine;
use Phil\CommerceConsignmentBundle\Infrastructure\Memory\InMemoryConsignorRepository;
use Phil\CommerceConsignmentBundle\Infrastructure\Memory\InMemoryLedgerRepository;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\Fixtures;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\RecordingStockMovements;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UnsoldStock::class)]
#[CoversClass(ConsignmentRefused::class)]
final class UnsoldStockTest extends TestCase
{
    private Ledger $ledger;

    private ConsignorAgreements $agreements;

    private RecordingStockMovements $stock;

    private UnsoldStock $unsold;

    protected function setUp(): void
    {
        $repository = new InMemoryLedgerRepository();
        $consignors = new InMemoryConsignorRepository();
        $this->ledger = new Ledger($repository);
        $this->agreements = new ConsignorAgreements($consignors);
        $this->stock = new RecordingStockMovements();
        $this->unsold = new UnsoldStock($this->ledger, $repository, $consignors, $this->stock);
    }

    public function testUnitsAreDueAsTheAgreementAtTheirReceiptSays(): void
    {
        $author = Owner::consignor(Fixtures::AUTHOR);
        $this->agreements->agree($author, Fixtures::utc('2026-10-01'), 30, UnsoldPolicy::ReturnToConsignor, StatementFrequency::Monthly, Fixtures::utc('2026-10-01'));
        $this->agreements->agree(Owner::consignor('artisan-bo'), Fixtures::utc('2026-10-01'), 10, UnsoldPolicy::BuyOut, StatementFrequency::Monthly, Fixtures::utc('2026-10-01'));
        $this->agreements->agree(Owner::consignor('press-cy'), Fixtures::utc('2026-10-01'), 30, UnsoldPolicy::Extend, StatementFrequency::Monthly, Fixtures::utc('2026-10-01'), 15);
        $this->agreements->agree(Owner::consignor('endless-di'), Fixtures::utc('2026-10-01'), null, UnsoldPolicy::ReturnToConsignor, StatementFrequency::Monthly, Fixtures::utc('2026-10-01'));

        $this->ledger->receive(Fixtures::mixedReceipt('DN-1'), Fixtures::utc('2026-10-05 10:00:00'));
        $this->ledger->receive(Fixtures::consigned(2, 'artisan-bo', ConsignmentTerms::commission(1), 'CS-2'), Fixtures::utc('2026-10-20 10:00:00'));
        $this->ledger->receive(Fixtures::consigned(3, 'press-cy', ConsignmentTerms::commission(1), 'CS-3'), Fixtures::utc('2026-10-06 10:00:00'));
        $this->ledger->receive(Fixtures::consigned(1, 'endless-di', ConsignmentTerms::commission(1), 'CS-4'), Fixtures::utc('2026-10-02 10:00:00'));
        $this->ledger->receive(Fixtures::consigned(1, 'early-ed', ConsignmentTerms::commission(1), 'CS-5'), Fixtures::utc('2026-09-02 10:00:00'));
        $this->ledger->receive(Fixtures::consigned(4, Fixtures::AUTHOR, ConsignmentTerms::commission(1), 'CS-6'), Fixtures::utc('2026-09-30 23:59:59'));

        // Units received before a consignor's first agreement, or under one without a term, are never due. A new agreement
        // with the author does not change the units received under the first.
        $this->agreements->agree($author, Fixtures::utc('2026-11-10'), 90, UnsoldPolicy::BuyOut, StatementFrequency::Monthly, Fixtures::utc('2026-11-10'));
        $this->ledger->receive(Fixtures::consigned(6, Fixtures::AUTHOR, ConsignmentTerms::commission(1), 'CS-7'), Fixtures::utc('2026-11-12 10:00:00'));

        self::assertSame([], $this->unsold->due(Fixtures::utc('2026-10-30 09:59:59')));
        self::assertSame([['artisan-bo', 2, '2026-10-30 10:00', 'buy_out']], $this->summary($this->unsold->due(Fixtures::utc('2026-10-30 10:00:00'))));

        $due = $this->unsold->due(Fixtures::utc('2026-12-01'));
        self::assertSame([
            ['artisan-bo', 2, '2026-10-30 10:00', 'buy_out'],
            [Fixtures::AUTHOR, 5, '2026-11-04 10:00', 'return'],
            ['press-cy', 3, '2026-11-20 10:00', 'return'],
        ], $this->summary($due));
        self::assertSame([Fixtures::BOOK, Fixtures::SHELF, '2026-10-05'], [$due[1]->sku, $due[1]->location, $due[1]->receivedAt->format('Y-m-d')]);
        self::assertNotSame('', $due[1]->layer);

        self::assertSame([[Fixtures::AUTHOR, 5, '2026-11-04 10:00', 'return']], $this->summary($this->unsold->due(Fixtures::utc('2026-12-01'), $author)));
        self::assertSame([['artisan-bo', 2, '2026-10-30 10:00', 'buy_out']], $this->summary($this->unsold->due(Fixtures::utc('2026-12-01'), limit: 1)));
        self::assertSame([['artisan-bo', 2, '2026-10-30 10:00', 'buy_out']], $this->summary($this->unsold->due(Fixtures::utc('2026-12-01'), limit: 0)));
        self::assertSame([
            ['artisan-bo', 2, '2026-10-30 10:00', 'buy_out'],
            [Fixtures::AUTHOR, 5, '2026-11-04 10:00', 'return'],
            ['press-cy', 3, '2026-11-20 10:00', 'return'],
            [Fixtures::AUTHOR, 6, '2027-02-10 10:00', 'buy_out'],
        ], $this->summary($this->unsold->due(Fixtures::utc('2027-02-10 10:00:00'))));
    }

    public function testFiveHundredLayersAreListedByDefault(): void
    {
        $this->agreements->agree(Owner::consignor(Fixtures::AUTHOR), Fixtures::utc('2026-10-01'), 1, UnsoldPolicy::ReturnToConsignor, StatementFrequency::Monthly, Fixtures::utc('2026-10-01'));
        $this->ledger->receive(Receipt::of('CS-1', Fixtures::SHELF, array_map(static fn (int $i): ReceiptLine => ReceiptLine::consigned('S'.$i, 1, Fixtures::AUTHOR, ConsignmentTerms::commission(1)), range(1, 500))), Fixtures::utc('2026-10-02'));
        self::assertCount(500, $this->unsold->due(Fixtures::utc('2026-11-01')));

        $this->ledger->receive(Fixtures::consigned(1, Fixtures::AUTHOR, ConsignmentTerms::commission(1)), Fixtures::utc('2026-10-02'));
        self::assertCount(500, $this->unsold->due(Fixtures::utc('2026-11-01')));
        self::assertCount(501, $this->unsold->due(Fixtures::utc('2026-11-01'), limit: 501));
    }

    public function testUnitsGivenBackLeaveTheLedgerAndTheShelf(): void
    {
        $author = Owner::consignor(Fixtures::AUTHOR);
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(9));
        $this->stock->onHand[Fixtures::BOOK.'@'.Fixtures::SHELF] = 10;

        $slip = $this->unsold->giveBack('RS-1', $author, Fixtures::SHELF, [Fixtures::BOOK => 4], Fixtures::at(12));

        self::assertSame(4, $slip->quantity());
        self::assertSame(['give back 4 BOOK-1 at store (RS-1)'], $this->stock->calls);
        self::assertSame(1, $this->ledger->held(Fixtures::BOOK, Fixtures::SHELF, $author));
    }

    public function testASlipLargerThanTheShelfMovesNothing(): void
    {
        $author = Owner::consignor(Fixtures::AUTHOR);
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(9));
        $this->stock->onHand[Fixtures::BOOK.'@'.Fixtures::SHELF] = 3;

        try {
            $this->unsold->giveBack('RS-1', $author, Fixtures::SHELF, [Fixtures::BOOK => 4], Fixtures::at(12));
            self::fail('More units than on the shelf were given back.');
        } catch (ConsignmentRefused $consignmentRefused) {
            self::assertSame('The SKU BOOK-1 holds 3 unit(s) at store in the ledger; 4 were asked.', $consignmentRefused->getMessage());
        }

        self::assertSame([], $this->stock->calls);
        self::assertSame(5, $this->ledger->held(Fixtures::BOOK, Fixtures::SHELF, $author));
        self::assertSame(3, $this->unsold->giveBack('RS-1', $author, Fixtures::SHELF, [Fixtures::BOOK => 3], Fixtures::at(12))->quantity());

        $this->stock->calls = [];

        try {
            $this->unsold->giveBack('RS-2', $author, Fixtures::SHELF, [Fixtures::BOOK => 3], Fixtures::at(12));
            self::fail('More units than the consignor holds were given back.');
        } catch (ConsignmentRefused $consignmentRefused) {
            self::assertSame('The SKU BOOK-1 holds 2 unit(s) at store in the ledger; 3 were asked.', $consignmentRefused->getMessage());
        }

        self::assertSame([], $this->stock->calls);
        $this->expectException(InvalidValue::class);
        $this->unsold->giveBack('RS-3', $author, Fixtures::SHELF, [Fixtures::BOOK => -1], Fixtures::at(12));
    }

    public function testTheShopBuysUnitsWithoutMovingTheStock(): void
    {
        $author = Owner::consignor(Fixtures::AUTHOR);
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(9));

        $slip = $this->unsold->buyOut('BO-1', $author, Fixtures::BOOK, Fixtures::SHELF, 2, Fixtures::cad(1000), Fixtures::at(12));

        self::assertSame(2000, $slip->owed()?->minorUnits);
        self::assertSame([], $this->stock->calls);
        self::assertSame(7, $this->ledger->held(Fixtures::BOOK, Fixtures::SHELF, Owner::shop()));
    }

    /**
     * @param list<DueUnits> $due
     *
     * @return list<array{string, int, string, string}>
     */
    private function summary(array $due): array
    {
        return array_map(static fn (DueUnits $units): array => [$units->consignor->key(), $units->units, $units->dueAt->format('Y-m-d H:i'), $units->action->value], $due);
    }
}
