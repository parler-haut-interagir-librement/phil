<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Unit\Application\Export;

use DateTimeImmutable;
use Override;
use Phil\DataTableBundle\Application\Contract\DataSource;
use Phil\DataTableBundle\Application\Definition\Column;
use Phil\DataTableBundle\Application\Definition\Filter;
use Phil\DataTableBundle\Application\Definition\TableBuilder;
use Phil\DataTableBundle\Application\Definition\TableDefinition;
use Phil\DataTableBundle\Application\Export\ExportedRows;
use Phil\DataTableBundle\Application\Export\ExportRows;
use Phil\DataTableBundle\Application\OpenTable;
use Phil\DataTableBundle\Application\Query\ResultPage;
use Phil\DataTableBundle\Application\Query\TableQuery;
use Phil\DataTableBundle\Application\Source\InMemoryDataSource;
use Phil\DataTableBundle\Application\State\StateResolver;
use Phil\DataTableBundle\Application\State\TableState;
use Phil\DataTableBundle\Tests\Fixtures\Kit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

#[CoversClass(ExportRows::class)]
#[CoversClass(ExportedRows::class)]
final class ExportRowsTest extends TestCase
{
    public function testTheRowsAreThoseOfTheTableInTheirKinds(): void
    {
        $rows = $this->rows($this->table(), new TableState('-title', filters: ['q' => 'Row 0']));

        self::assertSame('Les lignes', $rows->title);
        self::assertSame(['Titre', 'Pages', 'Ratio', 'When', 'Status', 'Print', 'Site'], $rows->headings);
        self::assertSame(9, $rows->total);
        $all = iterator_to_array($rows->rows, false);
        self::assertCount(9, $all);
        [$title, $pages, $ratio, $when, $status, $print, $site] = $all[0];
        self::assertSame(['Row 09', 1009, 4.5, 'Vivant', false, 'https://example.test/9'], [$title, $pages, $ratio, $status, $print, $site]);
        self::assertInstanceOf(DateTimeImmutable::class, $when);
        self::assertSame('2026-01-11 07:00 -05:00', $when->format('Y-m-d H:i P'), 'in the zone of the column');
        self::assertSame(['Row 08', 1008, 4, 'Draft'], \array_slice($all[1], 0, 3) + [3 => $all[1][4]]);
    }

    public function testOnlyTheVisibleColumnsButBlocksAreWritten(): void
    {
        $rows = $this->rows($this->table(), new TableState(columns: ['pages', 'cover', 'title']));

        self::assertSame(['Titre', 'Pages'], $rows->headings);
        self::assertSame(['Row 01', 1001], iterator_to_array($rows->rows, false)[0]);
    }

    public function testAnEmptyValueStaysEmptyAndADateWithoutZoneTakesPhps(): void
    {
        $row = new stdClass();
        $row->id = 1;
        $row->title = null;
        $row->when = new DateTimeImmutable('2026-03-01 12:00 UTC');
        $row->pages = '12.5';

        $table = new TableBuilder('t')->caption('C')->column(Column::text('title', 'T'))->column(Column::date('when', 'W'))->column(Column::number('pages', 'P'))->build();
        $zone = date_default_timezone_get();
        date_default_timezone_set('America/Toronto');

        try {
            $cells = iterator_to_array($this->rows($table, new TableState(), [$row])->rows, false)[0];
        } finally {
            date_default_timezone_set($zone);
        }

        self::assertNull($cells[0]);
        self::assertInstanceOf(DateTimeImmutable::class, $cells[1]);
        self::assertSame('07:00', $cells[1]->format('H:i'));
        self::assertSame(12.5, $cells[2]);
    }

    public function testOnlyAColumnOfTheKindTakesTheKindOfItsValue(): void
    {
        $row = new stdClass();
        $row->id = 1;
        $row->code = '007';
        $row->text = 2.5;
        $row->when = '2026-03-01';
        $row->stamp = new DateTimeImmutable('2026-03-01 12:00 UTC');
        $row->flag = 1;
        $row->pages = '12';
        $row->size = '1.5';

        $table = new TableBuilder('t')->caption('C')
            ->column(Column::text('code', 'A'))
            ->column(Column::text('text', 'B'))
            ->column(Column::date('when', 'C'))
            ->column(Column::boolean('flag', 'D'))
            ->column(Column::number('pages', 'E'))
            ->column(Column::number('size', 'F'))
            ->column(Column::text('stamp', 'G'))
            ->build();
        $cells = iterator_to_array($this->rows($table, new TableState(), [$row])->rows, false)[0];

        self::assertSame(['007', '2.5', '2026-03-01', true, 12, 1.5, ''], $cells);
    }

    public function testTheRowsAreFetchedAPageAtATimeAndStopAtTheMost(): void
    {
        $source = new class implements DataSource {
            /** @var list<int> */
            public array $pages = [];

            #[Override]
            public function fetch(TableQuery $query): ResultPage
            {
                $this->pages[] = $query->page;
                $rows = [];

                for ($id = $query->offset() + 1; $id <= min(1200, $query->offset() + $query->pageSize); ++$id) {
                    $rows[] = ['id' => $id, 'title' => 'Row '.$id];
                }

                return new ResultPage($rows, 1200);
            }

            #[Override]
            public function find(array $ids): array
            {
                return [];
            }
        };
        $table = static fn (int $most): OpenTable => new OpenTable(new TableBuilder('t')->caption('C')->column(Column::text('title', 'T'))->exportable(maxRows: $most)->build(), $source);
        $export = new ExportRows(new StateResolver(), new Kit()->values, new Kit()->translator);

        $rows = $export->rows($table(10_000), new TableState());
        self::assertSame([1], $source->pages, 'the first page only, before the rows are read');
        $all = iterator_to_array($rows->rows, false);
        self::assertCount(1200, $all);
        self::assertSame(['Row 1200'], $all[1199]);
        self::assertSame([1, 2, 3], $source->pages);
        self::assertSame(500, ExportRows::PAGE);

        $source->pages = [];
        self::assertCount(500, iterator_to_array($export->rows($table(500), new TableState())->rows, false));
        self::assertSame([1], $source->pages, 'nothing is fetched past the most rows');

        $source->pages = [];
        self::assertCount(501, iterator_to_array($export->rows($table(501), new TableState())->rows, false));
        self::assertSame([1, 2], $source->pages);
    }

    public function testATableOfExactlyOnePageIsFetchedTwice(): void
    {
        $rows = [];

        for ($id = 1; $id <= ExportRows::PAGE; ++$id) {
            $rows[] = ['id' => $id, 'title' => 'Row '.$id];
        }

        $table = new TableBuilder('t')->caption('C')->column(Column::text('title', 'T'))->build();
        $exported = iterator_to_array($this->rows($table, new TableState(), $rows)->rows, false);

        self::assertCount(500, $exported);
    }

    /**
     * @param list<array<string, mixed>|object>|null $rows
     */
    private function rows(TableDefinition $table, TableState $state, ?array $rows = null): ExportedRows
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['Lines' => 'Les lignes', 'Title' => 'Titre', 'status.live' => 'Vivant'], 'fr', 'app');

        $kit = new Kit($translator);

        return new ExportRows(new StateResolver(), $kit->values, $translator)->rows(new OpenTable($table, new InMemoryDataSource($rows ?? $this->books())), $state);
    }

    private function table(): TableDefinition
    {
        return new TableBuilder('t')
            ->caption('Lines')
            ->translationDomain('app')
            ->column(Column::text('title', 'Title')->sortable())
            ->column(Column::number('pages', 'Pages', 0))
            ->column(Column::number('ratio', 'Ratio', 1))
            ->column(Column::date('when', 'When', timezone: 'America/Toronto'))
            ->column(Column::enum('status', 'Status', ['live' => 'status.live', 'draft' => 'Draft']))
            ->column(Column::boolean('print', 'Print'))
            ->column(Column::link('site', 'Site', static fn (array|object $row): string => '/')->reading(static fn (array|object $row): string => 'https://example.test/'.($row instanceof stdClass && \is_int($row->id) ? $row->id : 0)))
            ->column(Column::block('cover', 'Cover', 't.twig', 'cover'))
            ->filter(Filter::text('q', 'Search', ['title']))
            ->sortBy('title')
            ->exportable()
            ->build();
    }

    /**
     * @return list<stdClass>
     */
    private function books(): array
    {
        $rows = [];

        for ($id = 1; $id <= 30; ++$id) {
            $row = new stdClass();
            $row->id = $id;
            $row->title = \sprintf('Row %02d', $id);
            $row->pages = 1000 + $id;
            $row->ratio = $id / 2;
            $row->when = new DateTimeImmutable(\sprintf('2026-01-%02d 12:00 UTC', $id % 28 + 2));
            $row->status = 0 === $id % 2 ? 'draft' : 'live';
            $row->print = 0 !== $id % 3;
            $rows[] = $row;
        }

        return $rows;
    }
}
