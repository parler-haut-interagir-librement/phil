<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Unit\Application\State;

use Phil\DataTableBundle\Application\Definition\Column;
use Phil\DataTableBundle\Application\Definition\Filter;
use Phil\DataTableBundle\Application\Definition\TableBuilder;
use Phil\DataTableBundle\Application\Definition\TableDefinition;
use Phil\DataTableBundle\Application\Query\BooleanCriterion;
use Phil\DataTableBundle\Application\Query\ChoiceCriterion;
use Phil\DataTableBundle\Application\Query\DateRangeCriterion;
use Phil\DataTableBundle\Application\Query\Sort;
use Phil\DataTableBundle\Application\Query\SortDirection;
use Phil\DataTableBundle\Application\Query\TextCriterion;
use Phil\DataTableBundle\Application\State\ResolvedState;
use Phil\DataTableBundle\Application\State\StateResolver;
use Phil\DataTableBundle\Application\State\TableState;
use Phil\DataTableBundle\Tests\Fixtures\Status;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StateResolver::class)]
#[CoversClass(ResolvedState::class)]
#[CoversClass(TableState::class)]
final class StateResolverTest extends TestCase
{
    public function testTheStateByDefault(): void
    {
        self::assertSame([1, 0], [new TableState()->page, new TableState()->size]);
    }

    public function testTheDefaultsWithNothingAsked(): void
    {
        $resolved = $this->resolve(new TableState());

        self::assertSame('', $resolved->sort);
        self::assertSame(['title', SortDirection::Asc], [$resolved->active?->field, $resolved->active?->direction]);
        self::assertSame([['b.title', SortDirection::Asc], ['id', SortDirection::Asc]], $this->sorts($resolved));
        self::assertSame([1, 10], [$resolved->page, $resolved->pageSize]);
        self::assertSame([[], [], [], []], [$resolved->filters, $resolved->criteria, $resolved->columns, $resolved->selected]);
    }

    public function testASortIsAColumnThatSorts(): void
    {
        $asked = $this->resolve(new TableState(sort: '-pages'));
        self::assertSame('-pages', $asked->sort);
        self::assertSame(['pages', SortDirection::Desc], [$asked->active?->field, $asked->active?->direction]);
        self::assertSame([['pages', SortDirection::Desc], ['id', SortDirection::Asc]], $this->sorts($asked));

        self::assertSame(SortDirection::Asc, $this->resolve(new TableState(sort: 'pages'))->active?->direction);

        foreach (['nope', '-nope', 'title OR 1=1', 'note', '--pages'] as $unknown) {
            $resolved = $this->resolve(new TableState(sort: $unknown));
            self::assertSame(['', 'title'], [$resolved->sort, $resolved->active?->field], $unknown);
        }
    }

    public function testTheIdentifierTiesTheSortOnce(): void
    {
        $table = new TableBuilder('t')->caption('C')->column(Column::number('id', 'Id')->sortable())->sortBy('id', SortDirection::Desc)->build();

        self::assertSame([['id', SortDirection::Desc]], $this->sorts(new StateResolver()->resolve($table, new TableState())));
    }

    public function testNoSortByDefaultStillTiesOnTheIdentifier(): void
    {
        $table = new TableBuilder('t')->caption('C')->column(Column::text('a', 'A'))->build();
        $resolved = new StateResolver()->resolve($table, new TableState());

        self::assertNull($resolved->active);
        self::assertSame([['id', SortDirection::Asc]], $this->sorts($resolved));
    }

    public function testThePageAndItsSizeStayWithinTheDeclaration(): void
    {
        self::assertSame(1, $this->resolve(new TableState(page: -4))->page);
        self::assertSame(1, $this->resolve(new TableState(page: 0))->page);
        self::assertSame(7, $this->resolve(new TableState(page: 7))->page);
        self::assertSame(11, $this->resolve(new TableState(page: 11))->page, 'the furthest offset, 100, starts page 11');
        self::assertSame(11, $this->resolve(new TableState(page: 12))->page);
        self::assertSame(50, $this->resolve(new TableState(size: 50))->pageSize);
        self::assertSame(10, $this->resolve(new TableState(size: 7))->pageSize);
        self::assertSame(3, $this->resolve(new TableState(page: 99, size: 50))->page);
    }

    public function testTheLastReachablePage(): void
    {
        self::assertSame(11, StateResolver::lastReachablePage($this->table(), 10));
        self::assertSame(4, StateResolver::lastReachablePage($this->table(), 33));
        self::assertSame(2, StateResolver::lastReachablePage($this->table(), 60), 'a page that starts within the bound');
    }

    public function testTextFilters(): void
    {
        $resolved = $this->resolve(new TableState(filters: ['q' => '  Dune ', 'ignored' => 'x']));
        self::assertSame(['q' => 'Dune'], $resolved->filters);
        $criterion = $resolved->criteria[0];
        self::assertInstanceOf(TextCriterion::class, $criterion);
        self::assertSame([['title', 'isbn'], 'Dune'], [$criterion->fields, $criterion->text]);

        self::assertSame([], $this->resolve(new TableState(filters: ['q' => '   ']))->filters);
        self::assertSame([], $this->resolve(new TableState(filters: ['q' => ['a']]))->filters, 'an array from the address is dropped');
        self::assertSame(200, mb_strlen($this->resolve(new TableState(filters: ['q' => str_repeat('é', 300)]))->filters['q']));
    }

    public function testEnumFilters(): void
    {
        $resolved = $this->resolve(new TableState(filters: ['status' => 'draft']));
        $criterion = $resolved->criteria[0];
        self::assertInstanceOf(ChoiceCriterion::class, $criterion);
        self::assertSame(['state', 'draft'], [$criterion->field, $criterion->value]);
        self::assertSame(['status' => 'draft'], $resolved->filters);
        self::assertSame([], $this->resolve(new TableState(filters: ['status' => 'other']))->criteria);
    }

    public function testBooleanFilters(): void
    {
        foreach (['1' => true, '0' => false] as $value => $expected) {
            $resolved = $this->resolve(new TableState(filters: ['inPrint' => (string) $value]));
            $criterion = $resolved->criteria[0];
            self::assertInstanceOf(BooleanCriterion::class, $criterion);
            self::assertSame(['in_print', $expected], [$criterion->field, $criterion->value]);
            self::assertSame(['inPrint' => (string) $value], $resolved->filters);
        }

        self::assertSame([], $this->resolve(new TableState(filters: ['inPrint' => 'yes']))->criteria);
    }

    public function testDateRangeFilters(): void
    {
        $resolved = $this->resolve(new TableState(filters: ['day_from' => '2026-03-01', 'day_until' => '2026-03-02']));
        self::assertSame(['day_from' => '2026-03-01', 'day_until' => '2026-03-02'], $resolved->filters);
        $criterion = $resolved->criteria[0];
        self::assertInstanceOf(DateRangeCriterion::class, $criterion);
        self::assertSame('day', $criterion->field);
        self::assertSame('2026-03-01T00:00:00-05:00', $criterion->from?->format('c'));
        self::assertSame('2026-03-03T00:00:00-05:00', $criterion->before?->format('c'), 'the last day is included');
    }

    public function testADateRangeTakesOneBoundOrSwapsThem(): void
    {
        $from = $this->resolve(new TableState(filters: ['day_from' => '2026-03-01']));
        $this->assertInstanceOf(DateRangeCriterion::class, $from->criteria[0]);
        self::assertSame([['day_from' => '2026-03-01'], null], [$from->filters, $from->criteria[0]->before]);

        $until = $this->resolve(new TableState(filters: ['day_until' => '2026-03-01']));
        $this->assertInstanceOf(DateRangeCriterion::class, $until->criteria[0]);
        self::assertSame([['day_until' => '2026-03-01'], null], [$until->filters, $until->criteria[0]->from]);

        $swapped = $this->resolve(new TableState(filters: ['day_from' => '2026-03-05', 'day_until' => '2026-03-01']));
        self::assertSame(['day_from' => '2026-03-01', 'day_until' => '2026-03-05'], $swapped->filters);

        $same = $this->resolve(new TableState(filters: ['day_from' => '2026-03-05', 'day_until' => '2026-03-05']));
        self::assertSame(['day_from' => '2026-03-05', 'day_until' => '2026-03-05'], $same->filters);
    }

    public function testAFilterAfterADateRangeStillApplies(): void
    {
        self::assertSame(['after' => 'x'], $this->resolve(new TableState(filters: ['after' => 'x']))->filters);
    }

    public function testADayThatIsNotOneIsDropped(): void
    {
        foreach (['2026-02-31', '2026-3-1', 'x', "2026-03-01\n", ''] as $day) {
            self::assertSame([], $this->resolve(new TableState(filters: ['day_from' => $day]))->criteria, $day);
        }

        self::assertSame([], $this->resolve(new TableState(filters: ['day_from' => ['2026-03-01']]))->criteria);
    }

    public function testADateRangeInTheDefaultZone(): void
    {
        $table = new TableBuilder('t')->caption('C')->column(Column::text('a', 'A'))->filter(Filter::dateRange('d', 'D'))->build();
        $criterion = new StateResolver()->resolve($table, new TableState(filters: ['d_from' => '2026-03-01']))->criteria[0];
        $this->assertInstanceOf(DateRangeCriterion::class, $criterion);

        self::assertSame(date_default_timezone_get(), $criterion->from?->getTimezone()->getName());
    }

    public function testTheVisibleColumns(): void
    {
        self::assertSame(['title', 'note'], $this->resolve(new TableState(columns: ['note', 'title', 'nope', 3]))->columns, 'in the order of the table');
        self::assertSame([], $this->resolve(new TableState(columns: ['title', 'pages', 'note']))->columns, 'all shown');
        self::assertSame([], $this->resolve(new TableState(columns: ['nope']))->columns, 'none known');
    }

    public function testTheSelection(): void
    {
        self::assertSame(['a', 'b'], $this->resolve(new TableState(selected: ['a', 3, 'a', ['x'], 'b']))->selected);
        self::assertSame(['a'], $this->resolve(new TableState(selected: ['key' => 'a']))->selected);
        self::assertCount(TableBuilder::MAX_PAGE_SIZE, $this->resolve(new TableState(selected: array_map(strval(...), range(1, 700))))->selected);
    }

    private function resolve(TableState $state): ResolvedState
    {
        return new StateResolver()->resolve($this->table(), $state);
    }

    /**
     * @return list<array{string, SortDirection}>
     */
    private function sorts(ResolvedState $state): array
    {
        return array_map(static fn (Sort $sort): array => [$sort->field, $sort->direction], $state->sorts);
    }

    private function table(): TableDefinition
    {
        return new TableBuilder('t')
            ->caption('C')
            ->column(Column::text('title', 'T')->sortable('b.title'))
            ->column(Column::number('pages', 'P')->sortable())
            ->column(Column::text('note', 'N'))
            ->filter(Filter::text('q', 'Q', ['title', 'isbn']))
            ->filter(Filter::enum('status', 'S', Status::class, 'state'))
            ->filter(Filter::boolean('inPrint', 'I', 'in_print'))
            ->filter(Filter::dateRange('day', 'D', timezone: 'America/Toronto'))
            ->filter(Filter::text('after', 'A'))
            ->sortBy('title')
            ->pageSizes(10, 50)
            ->maxOffset(100)
            ->build();
    }
}
