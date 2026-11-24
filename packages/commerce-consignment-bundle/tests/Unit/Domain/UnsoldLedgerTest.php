<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Domain;

use Phil\CommerceConsignmentBundle\Domain\Exception\ConsignmentRefused;
use Phil\CommerceConsignmentBundle\Domain\Exception\InvalidValue;
use Phil\CommerceConsignmentBundle\Domain\Model\LedgerEntry;
use Phil\CommerceConsignmentBundle\Domain\Model\StockLayer;
use Phil\CommerceConsignmentBundle\Domain\Service\Ledger;
use Phil\CommerceConsignmentBundle\Domain\Stock\IssueOrder;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ConsignmentTerms;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Receipt;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ReceiptLine;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ReturnSlip;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\SlipLine;
use Phil\CommerceConsignmentBundle\Infrastructure\Memory\InMemoryLedgerRepository;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\Fixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unsold units given back to their consignor, or bought by the shop, in the ledger.
 */
#[CoversClass(Ledger::class)]
#[CoversClass(StockLayer::class)]
#[CoversClass(ReturnSlip::class)]
#[CoversClass(SlipLine::class)]
#[CoversClass(ConsignmentRefused::class)]
final class UnsoldLedgerTest extends TestCase
{
    private InMemoryLedgerRepository $repository;

    private Ledger $ledger;

    protected function setUp(): void
    {
        $this->repository = new InMemoryLedgerRepository();
        $this->ledger = new Ledger($this->repository);
    }

    public function testUnitsGivenBackLeaveTheConsignorsOldestLayersOnly(): void
    {
        $this->ledger->receive(Fixtures::mixedReceipt('DN-1'), Fixtures::at(9));
        $this->ledger->receive(Fixtures::consigned(4, Fixtures::AUTHOR, ConsignmentTerms::commission(250_000), 'CS-2'), Fixtures::at(10));
        $this->ledger->receive(Fixtures::consigned(3, 'artisan-bo', ConsignmentTerms::commission(1), 'CS-3'), Fixtures::at(8));
        $this->ledger->receive(Receipt::of('CS-4', Fixtures::SHELF, [ReceiptLine::consigned('PEN', 2, Fixtures::AUTHOR, ConsignmentTerms::commission(1))]), Fixtures::at(11));

        $slip = $this->ledger->giveBack('RS-1', Owner::consignor(Fixtures::AUTHOR), Fixtures::SHELF, [Fixtures::BOOK => 7, 'PEN' => 1], Fixtures::at(12));

        self::assertSame('RS-1', $slip->reference);
        self::assertSame(Fixtures::AUTHOR, $slip->consignor->consignor);
        self::assertSame(Fixtures::SHELF, $slip->location);
        self::assertSame(8, $slip->quantity());
        self::assertNull($slip->owed());
        self::assertSame([[Fixtures::BOOK, 5], [Fixtures::BOOK, 2], ['PEN', 1]], array_map(static fn (SlipLine $line): array => [$line->sku, $line->quantity], $slip->lines));
        self::assertNull($slip->lines[0]->shopLayer);
        self::assertSame(5, $this->ledger->held(Fixtures::BOOK, Fixtures::SHELF, Owner::shop()));
        self::assertSame(2, $this->ledger->held(Fixtures::BOOK, Fixtures::SHELF, Owner::consignor(Fixtures::AUTHOR)));
        self::assertSame(3, $this->ledger->held(Fixtures::BOOK, Fixtures::SHELF, Owner::consignor('artisan-bo')));
        self::assertSame(1, $this->ledger->held('PEN', Fixtures::SHELF));

        $entries = $this->repository->entriesOf('RS-1', Fixtures::BOOK);
        self::assertSame([['given_back', -5], ['given_back', -2]], array_map(static fn (LedgerEntry $entry): array => [$entry->kind->value, $entry->quantity], $entries));
        self::assertNull($entries[0]->margin());
        self::assertSame($slip->lines[0]->layer, $entries[0]->layer);
    }

    public function testAReturnSlipLargerThanWhatIsLeftMovesNothing(): void
    {
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(9));
        $this->ledger->receive(Receipt::of('CS-4', Fixtures::SHELF, [ReceiptLine::consigned('PEN', 2, Fixtures::AUTHOR, ConsignmentTerms::commission(1))]), Fixtures::at(11));

        try {
            $this->ledger->giveBack('RS-1', Owner::consignor(Fixtures::AUTHOR), Fixtures::SHELF, ['PEN' => 2, Fixtures::BOOK => 6], Fixtures::at(12));
            self::fail('A slip larger than the stock was given back.');
        } catch (ConsignmentRefused $consignmentRefused) {
            self::assertSame('The SKU BOOK-1 holds 5 unit(s) at store in the ledger; 6 were asked.', $consignmentRefused->getMessage());
        }

        self::assertSame(2, $this->ledger->held('PEN', Fixtures::SHELF));
        self::assertSame(10, $this->ledger->held(Fixtures::BOOK, Fixtures::SHELF));
        self::assertSame([], $this->repository->entriesOf('RS-1', 'PEN'));
    }

    public function testAReturnSlipIsChecked(): void
    {
        $this->ledger->receive(Fixtures::mixedReceipt(), Fixtures::at(9));
        $author = Owner::consignor(Fixtures::AUTHOR);

        foreach ([
            "A return slip concerns a consignor's units, not the shop's." => fn (): ReturnSlip => $this->ledger->giveBack('RS-1', Owner::shop(), Fixtures::SHELF, [Fixtures::BOOK => 1], Fixtures::at(12)),
            'A return slip holds 1 to 500 SKUs; 0 were given.' => fn (): ReturnSlip => $this->ledger->giveBack('RS-1', $author, Fixtures::SHELF, [], Fixtures::at(12)),
            'A return slip holds 1 to 500 SKUs; 501 were given.' => fn (): ReturnSlip => $this->ledger->giveBack('RS-1', $author, Fixtures::SHELF, array_fill_keys(array_map(static fn (int $i): string => 'S'.$i, range(0, 500)), 1), Fixtures::at(12)),
            'A quantity is one unit or more; 0 is not.' => fn (): ReturnSlip => $this->ledger->giveBack('RS-1', $author, Fixtures::SHELF, [Fixtures::BOOK => 0], Fixtures::at(12)),
            'A reference has 1 to 64 characters, none of them a control character; "" does not.' => fn (): ReturnSlip => $this->ledger->giveBack('', $author, Fixtures::SHELF, [Fixtures::BOOK => 1], Fixtures::at(12)),
        ] as $message => $giveBack) {
            try {
                $giveBack();
                self::fail($message);
            } catch (ConsignmentRefused|InvalidValue $refused) {
                self::assertSame($message, $refused->getMessage());
            }
        }

        self::assertSame(10, $this->ledger->held(Fixtures::BOOK, Fixtures::SHELF));
    }

    public function testFiveHundredSkusFitOneSlip(): void
    {
        $lines = [];

        foreach (range(1, 500) as $i) {
            $lines[] = ReceiptLine::consigned('S'.$i, 1, Fixtures::AUTHOR, ConsignmentTerms::commission(1));
        }

        $this->ledger->receive(Receipt::of('CS-1', Fixtures::SHELF, $lines), Fixtures::at(9));
        $slip = $this->ledger->giveBack('RS-1', Owner::consignor(Fixtures::AUTHOR), Fixtures::SHELF, array_fill_keys(array_map(static fn (int $i): string => 'S'.$i, range(1, 500)), 1), Fixtures::at(12));

        self::assertSame(500, $slip->quantity());
    }

    public function testTheShopBuysUnitsAtTheirShareOrAtTheCostGiven(): void
    {
        $this->ledger->receive(Fixtures::consigned(2, Fixtures::AUTHOR, ConsignmentTerms::share(Fixtures::cad(1500)), 'CS-1'), Fixtures::at(9));
        $this->ledger->receive(Fixtures::consigned(3, Fixtures::AUTHOR, ConsignmentTerms::share(Fixtures::cad(1400)), 'CS-2'), Fixtures::at(10));

        $author = Owner::consignor(Fixtures::AUTHOR);

        $slip = $this->ledger->buyOut('BO-1', $author, Fixtures::BOOK, Fixtures::SHELF, 3, null, Fixtures::at(12));

        self::assertSame(3, $slip->quantity());
        self::assertSame(1500 * 2 + 1400, $slip->owed()?->minorUnits);
        self::assertSame([[2, 3000], [1, 1400]], array_map(static fn (SlipLine $line): array => [$line->quantity, $line->owed?->minorUnits], $slip->lines));
        self::assertSame(2, $this->ledger->held(Fixtures::BOOK, Fixtures::SHELF, $author));
        self::assertSame(3, $this->ledger->held(Fixtures::BOOK, Fixtures::SHELF, Owner::shop()));

        $shop = $this->repository->layer((string) $slip->lines[0]->shopLayer);
        self::assertInstanceOf(StockLayer::class, $shop);
        self::assertTrue($shop->owner()->equals(Owner::shop()));
        self::assertSame(1500, $shop->unitCost()?->minorUnits);
        self::assertNull($shop->terms());
        self::assertSame($shop->id, $shop->origin);
        self::assertSame('BO-1', $shop->reference);
        self::assertSame(2, $shop->received);
        self::assertSame(Fixtures::SHELF, $shop->location);
        self::assertSame(Fixtures::BOOK, $shop->sku);
        self::assertSame('2026-11-20 17:00:00 UTC', $shop->receivedAt()->format('Y-m-d H:i:s T'));

        $entries = $this->repository->entriesOf('BO-1', Fixtures::BOOK);
        self::assertSame([['bought_out', -2], ['bought_in', 2], ['bought_out', -1], ['bought_in', 1]], array_map(static fn (LedgerEntry $entry): array => [$entry->kind->value, $entry->quantity], $entries));
        self::assertSame([3000, 3000, 0], [$entries[0]->margin()?->revenue->minorUnits, $entries[0]->margin()?->cost->minorUnits, $entries[0]->margin()?->margin->minorUnits]);
        self::assertSame(1500, $entries[0]->unitPrice()?->minorUnits);
        self::assertTrue($entries[1]->owner()->equals(Owner::shop()));
        self::assertNull($entries[1]->margin());

        $sale = $this->ledger->sell('ORD-1', Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::cad(2000), IssueOrder::OwnedFirst, Fixtures::at(13));
        self::assertSame(500, $sale->margin()?->margin->minorUnits);
        self::assertSame([], $sale->owedToConsignors());

        $bought = $this->ledger->buyOut('BO-2', $author, Fixtures::BOOK, Fixtures::SHELF, 2, Fixtures::cad(900), Fixtures::at(14));
        self::assertSame(1800, $bought->owed()?->minorUnits);
        self::assertSame(0, $this->ledger->held(Fixtures::BOOK, Fixtures::SHELF, $author));
    }

    public function testABuyOutIsChecked(): void
    {
        $this->ledger->receive(Fixtures::consigned(1, Fixtures::AUTHOR, ConsignmentTerms::share(Fixtures::cad(700)), 'CS-0'), Fixtures::at(7));
        $this->ledger->receive(Fixtures::consigned(2, Fixtures::AUTHOR, ConsignmentTerms::commission(300_000), 'CS-1'), Fixtures::at(9));
        $this->ledger->receive(Fixtures::purchased(4, 1000), Fixtures::at(8));

        $author = Owner::consignor(Fixtures::AUTHOR);

        foreach ([
            'The units of BOOK-1 that the consignor author-ann left on a commission have no agreed cost: give the unit cost of the buy-out.' => fn (): ReturnSlip => $this->ledger->buyOut('BO-1', $author, Fixtures::BOOK, Fixtures::SHELF, 2, null, Fixtures::at(12)),
            "A buy-out concerns a consignor's units, not the shop's." => fn (): ReturnSlip => $this->ledger->buyOut('BO-1', Owner::shop(), Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::cad(1), Fixtures::at(12)),
            'A unit cost is zero or more; -0.01 is not.' => fn (): ReturnSlip => $this->ledger->buyOut('BO-1', $author, Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::cad(-1), Fixtures::at(12)),
            'The SKU BOOK-1 holds 3 unit(s) at store in the ledger; 4 were asked.' => fn (): ReturnSlip => $this->ledger->buyOut('BO-1', $author, Fixtures::BOOK, Fixtures::SHELF, 4, Fixtures::cad(1), Fixtures::at(12)),
            'A quantity is one unit or more; 0 is not.' => fn (): ReturnSlip => $this->ledger->buyOut('BO-1', $author, Fixtures::BOOK, Fixtures::SHELF, 0, Fixtures::cad(1), Fixtures::at(12)),
            'A reference has 1 to 64 characters, none of them a control character; " " does not.' => fn (): ReturnSlip => $this->ledger->buyOut(' ', $author, Fixtures::BOOK, Fixtures::SHELF, 1, Fixtures::cad(1), Fixtures::at(12)),
        ] as $message => $buyOut) {
            try {
                $buyOut();
                self::fail($message);
            } catch (ConsignmentRefused|InvalidValue $refused) {
                self::assertSame($message, $refused->getMessage());
            }
        }

        self::assertSame(3, $this->ledger->held(Fixtures::BOOK, Fixtures::SHELF, $author));
        self::assertSame(4, $this->ledger->held(Fixtures::BOOK, Fixtures::SHELF, Owner::shop()));
        self::assertSame([], $this->repository->entriesOf('BO-1', Fixtures::BOOK));
        self::assertSame(700, $this->ledger->buyOut('BO-2', $author, Fixtures::BOOK, Fixtures::SHELF, 1, null, Fixtures::at(12))->owed()?->minorUnits);
        self::assertSame(0, $this->ledger->buyOut('BO-3', $author, Fixtures::BOOK, Fixtures::SHELF, 2, Fixtures::cad(0), Fixtures::at(12))->owed()?->minorUnits);
    }
}
