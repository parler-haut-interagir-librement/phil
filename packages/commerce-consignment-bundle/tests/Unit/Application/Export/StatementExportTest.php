<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Application\Export;

use Phil\CommerceConsignmentBundle\Application\Export\StatementCsv;
use Phil\CommerceConsignmentBundle\Application\Export\StatementView;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignorStatement;
use Phil\CommerceConsignmentBundle\Domain\Model\LedgerEntry;
use Phil\CommerceConsignmentBundle\Domain\Model\StockLayer;
use Phil\CommerceConsignmentBundle\Domain\Stock\EntryKind;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ConsignmentTerms;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\HeldUnits;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Margin;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ReceiptLine;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\StatementLine;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\StatementPeriod;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\Fixtures;
use Phil\Money\Currency;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

#[CoversClass(StatementCsv::class)]
#[CoversClass(StatementView::class)]
final class StatementExportTest extends TestCase
{
    public function testTheCsvHasOneRowPerLineThenTheTotals(): void
    {
        self::assertSame(
            "statement,consignor,currency,moved_at,reference,sku,location,kind,units,revenue,share,commission\n"
            ."ST-00000007,author-ann,CAD,2026-11-20T14:00:00Z,\"'=HYPERLINK(\"\"x\"\")\",BOOK-1,store,sold,2,40.00,30.00,10.00\n"
            ."ST-00000007,author-ann,CAD,2026-11-20T15:00:00Z,ORD-1,'-BOOK,store,returned,-1,-20.00,-15.00,-5.00\n"
            ."ST-00000007,author-ann,CAD,2026-11-20T16:00:00Z,,BOOK-1,store,given_back,3,0.00,0.00,0.00\n"
            ."ST-00000007,author-ann,CAD,2026-11-21T00:00:00Z,total,,,,1,20.00,15.00,5.00\n",
            new StatementCsv()->write($this->statement()),
        );
    }

    public function testTheTotalCountsTheUnitsBoughtOut(): void
    {
        $line = StatementLine::fromEntry(LedgerEntry::sale($this->layer(), EntryKind::BoughtOut, -2, 'BO-1', Fixtures::cad(900), Margin::of(Fixtures::cad(1800), Fixtures::cad(1800)), Fixtures::at(9)), Currency::of('CAD'));
        $csv = new StatementCsv()->write(ConsignorStatement::issue('ST-1', Owner::consignor('c'), Currency::of('CAD'), $this->period(), [$line], [], Fixtures::at(23)));

        self::assertStringEndsWith("ST-1,c,CAD,2026-11-21T00:00:00Z,total,,,,2,18.00,18.00,0.00\n", $csv);
    }

    public function testFormulasAreMadePlainText(): void
    {
        foreach (['=a' => "'=a", '+a' => "'+a", '-a' => "'-a", '@a' => "'@a", 'a=b' => 'a=b'] as $consignor => $cell) {
            $csv = new StatementCsv()->write(ConsignorStatement::issue('ST-1', Owner::consignor($consignor), Currency::of('CAD'), $this->period(), [], [], Fixtures::at(23)));

            self::assertSame(['ST-1', $cell, 'CAD'], \array_slice(str_getcsv(explode("\n", $csv)[1], escape: ''), 0, 3), $consignor);
        }
    }

    public function testTheViewHoldsStringsAndIntegersOnly(): void
    {
        $data = new StatementView()->data($this->statement());

        self::assertSame('@PhilCommerceConsignment/statement.html.twig', StatementView::TEMPLATE);
        self::assertSame(['ST-00000007', Fixtures::AUTHOR, 'CAD', '2026-11-20T00:00:00Z', '2026-11-21T00:00:00Z', '2026-11-21T04:00:00Z'], [$data['number'], $data['consignor'], $data['currency'], $data['period_start'], $data['period_end'], $data['issued_at']]);
        self::assertSame([2, 1, 0, 3, 4], [$data['units_sold'], $data['units_returned'], $data['units_bought_out'], $data['units_given_back'], $data['units_held']]);
        self::assertSame(['20.00', '15.00', '5.00'], [$data['revenue'], $data['share'], $data['commission']]);
        self::assertSame(['moved_at' => '2026-11-20T15:00:00Z', 'reference' => 'ORD-1', 'sku' => '-BOOK', 'location' => Fixtures::SHELF, 'kind' => 'returned', 'units' => -1, 'revenue' => '-20.00', 'share' => '-15.00', 'commission' => '-5.00'], $data['lines'][1]);
        self::assertSame('', $data['lines'][2]['reference']);
        self::assertSame([['sku' => Fixtures::BOOK, 'location' => Fixtures::SHELF, 'units' => 4, 'received_at' => '2026-11-01T00:00:00Z', 'age_in_days' => 20]], $data['held']);
    }

    public function testTheTemplateEscapesEveryValue(): void
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 4).'/templates', 'PhilCommerceConsignment');

        $html = new Environment($loader, ['strict_variables' => true])->render(StatementView::TEMPLATE, ['statement' => new StatementView()->data($this->statement())]);

        self::assertStringContainsString('<h1>Statement ST-00000007</h1>', $html);
        self::assertStringContainsString('<strong>15.00 CAD</strong>', $html);
        self::assertStringContainsString('=HYPERLINK(&quot;x&quot;)', $html);
        self::assertStringContainsString('<td>20</td>', $html);
        self::assertStringNotContainsString('No entry over the period.', $html);

        $empty = new Environment($loader, ['strict_variables' => true])->render(StatementView::TEMPLATE, ['statement' => new StatementView()->data(ConsignorStatement::issue('ST-2', Owner::consignor('<b>'), Currency::of('CAD'), $this->period(), [], [], Fixtures::at(23)))]);
        self::assertStringContainsString('No entry over the period.', $empty);
        self::assertStringContainsString('No unit left in the shop at the end of the period.', $empty);
        self::assertStringContainsString('&lt;b&gt;', $empty);
    }

    private function statement(): ConsignorStatement
    {
        $book = $this->layer();
        $odd = StockLayer::receive(ReceiptLine::consigned('-BOOK', 1, Fixtures::AUTHOR, ConsignmentTerms::commission(1)), Fixtures::SHELF, 'CS-2', Fixtures::at(8));
        $cad = Currency::of('CAD');
        $lines = [
            StatementLine::fromEntry(LedgerEntry::sale($book, EntryKind::Sold, -2, '=HYPERLINK("x")', Fixtures::cad(2000), Margin::of(Fixtures::cad(4000), Fixtures::cad(3000)), Fixtures::at(9)), $cad),
            StatementLine::fromEntry(LedgerEntry::sale($odd, EntryKind::Returned, 1, 'ORD-1', Fixtures::cad(2000), Margin::of(Fixtures::cad(-2000), Fixtures::cad(-1500)), Fixtures::at(10)), $cad),
            StatementLine::fromEntry(LedgerEntry::movement($book, EntryKind::GivenBack, -3, null, Fixtures::at(11)), $cad),
        ];

        return ConsignorStatement::issue('ST-00000007', Owner::consignor(Fixtures::AUTHOR), $cad, $this->period(), $lines, [HeldUnits::at($book->id, Fixtures::BOOK, Fixtures::SHELF, 4, Fixtures::utc('2026-11-01'), Fixtures::utc('2026-11-21'))], Fixtures::at(23));
    }

    private function layer(): StockLayer
    {
        return StockLayer::receive(ReceiptLine::consigned(Fixtures::BOOK, 10, Fixtures::AUTHOR, ConsignmentTerms::commission(1)), Fixtures::SHELF, 'CS-1', Fixtures::at(8));
    }

    private function period(): StatementPeriod
    {
        return StatementPeriod::of(Fixtures::utc('2026-11-20'), Fixtures::utc('2026-11-21'));
    }
}
