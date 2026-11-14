<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Unit\Application\Source;

use DateTimeImmutable;
use Phil\DataTableBundle\Application\Query\BooleanCriterion;
use Phil\DataTableBundle\Application\Query\ChoiceCriterion;
use Phil\DataTableBundle\Application\Query\DateRangeCriterion;
use Phil\DataTableBundle\Application\Query\ResultPage;
use Phil\DataTableBundle\Application\Query\Sort;
use Phil\DataTableBundle\Application\Query\SortDirection;
use Phil\DataTableBundle\Application\Query\TableQuery;
use Phil\DataTableBundle\Application\Query\TextCriterion;
use Phil\DataTableBundle\Application\Source\InMemoryDataSource;
use Phil\DataTableBundle\Tests\Fixtures\Book;
use Phil\DataTableBundle\Tests\Fixtures\Status;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryDataSource::class)]
#[CoversClass(TableQuery::class)]
#[CoversClass(ResultPage::class)]
#[CoversClass(Sort::class)]
#[CoversClass(TextCriterion::class)]
#[CoversClass(ChoiceCriterion::class)]
#[CoversClass(BooleanCriterion::class)]
#[CoversClass(DateRangeCriterion::class)]
final class InMemoryDataSourceTest extends TestCase
{
    public function testAPageAndTheCount(): void
    {
        $source = $this->source();
        $page = $source->fetch(new TableQuery([], [new Sort('id')], 2, 2));

        self::assertSame([[3, 4], 5], [$this->ids($page), $page->total]);
        self::assertSame(2, new TableQuery([], [], 2, 2)->offset());
        self::assertSame(3, new TableQuery([], [], 2, 2)->onPage(3)->page);
    }

    public function testSorts(): void
    {
        $source = $this->source();

        self::assertSame([5, 4, 3, 2, 1], $this->ids($source->fetch(new TableQuery([], [new Sort('id', SortDirection::Desc)], 1, 10))));
        self::assertSame([2, 4, 1, 3, 5], $this->ids($source->fetch(new TableQuery([], [new Sort('status'), new Sort('id')], 1, 10))), 'backed enums sort by their value, ties by the next sort');
        self::assertSame([4, 2, 5, 3, 1], $this->ids($source->fetch(new TableQuery([], [new Sort('status'), new Sort('id', SortDirection::Desc)], 1, 10))));
    }

    public function testFilters(): void
    {
        $source = $this->source();

        self::assertSame([3], $this->filtered($source, new TextCriterion(['title'], 'gAmMa')));
        self::assertSame([1, 2, 3, 4, 5], $this->filtered($source, new TextCriterion(['title'], 'a')));
        self::assertSame([], $this->filtered($source, new TextCriterion(['title'], 'zeta')));
        self::assertSame([2], $this->filtered($source, new TextCriterion(['title', 'pages'], '102')), 'any of the fields');
        self::assertSame([2, 4], $this->filtered($source, new ChoiceCriterion('status', 'draft')));
        self::assertSame([1, 3, 5], $this->filtered($source, new BooleanCriterion('inPrint', true)));
        self::assertSame([2, 4], $this->filtered($source, new BooleanCriterion('inPrint', false)));
        self::assertSame([2, 3], $this->filtered($source, new DateRangeCriterion('published', new DateTimeImmutable('2026-01-02'), new DateTimeImmutable('2026-01-04'))));
        self::assertSame([4, 5], $this->filtered($source, new DateRangeCriterion('published', new DateTimeImmutable('2026-01-04'), null)));
        self::assertSame([1], $this->filtered($source, new DateRangeCriterion('published', null, new DateTimeImmutable('2026-01-02'))));
        self::assertSame([1, 2, 3, 4, 5], $this->filtered($source, new DateRangeCriterion('published', null, null)));
    }

    public function testBooleansAndCaseAreReadLooselyInArrayRows(): void
    {
        $source = new InMemoryDataSource([['id' => 1, 'flag' => 1, 'name' => 'École'], ['id' => 2, 'flag' => 0, 'name' => 'Autre']]);

        self::assertSame([['id' => 1, 'flag' => 1, 'name' => 'École']], $source->fetch(new TableQuery([new BooleanCriterion('flag', true)], [], 1, 10))->rows);
        self::assertSame([['id' => 1, 'flag' => 1, 'name' => 'École']], $source->fetch(new TableQuery([new TextCriterion(['name'], 'éCO')], [], 1, 10))->rows);
    }

    public function testEveryFilterHolds(): void
    {
        $page = $this->source()->fetch(new TableQuery([new ChoiceCriterion('status', 'draft'), new TextCriterion(['title'], 'delta')], [], 1, 10));

        self::assertSame([4], $this->ids($page));
    }

    public function testADateRangeSkipsARowWithoutADate(): void
    {
        $source = new InMemoryDataSource([['id' => 1, 'day' => null]]);

        self::assertSame([], $source->fetch(new TableQuery([new DateRangeCriterion('day', null, null)], [], 1, 10))->rows);
    }

    public function testFindKeepsToItsRows(): void
    {
        $source = new InMemoryDataSource([['uuid' => 'a'], ['uuid' => 'b'], ['uuid' => 'c']], 'uuid');

        self::assertSame([['uuid' => 'a'], ['uuid' => 'c']], $source->find(['c', 'a', 'z']));
        self::assertSame([], $source->find([]));
    }

    /**
     * @return list<int>
     */
    private function filtered(InMemoryDataSource $source, TextCriterion|ChoiceCriterion|DateRangeCriterion|BooleanCriterion $criterion): array
    {
        return $this->ids($source->fetch(new TableQuery([$criterion], [new Sort('id')], 1, 10)));
    }

    /**
     * @return list<int>
     */
    private function ids(ResultPage $page): array
    {
        return array_map(static fn (array|object $row): int => $row instanceof Book ? $row->id : 0, $page->rows);
    }

    private function source(): InMemoryDataSource
    {
        $books = [];

        foreach (['Alpha', 'Bravta', 'Gamma', 'Delta', 'Etha'] as $position => $title) {
            $number = $position + 1;
            $books[] = new Book($number, $title, 100 + $number, new DateTimeImmutable('2026-01-0'.$number), 0 === $number % 2 ? Status::Draft : Status::Live, 1 === $number % 2);
        }

        return new InMemoryDataSource($books);
    }
}
