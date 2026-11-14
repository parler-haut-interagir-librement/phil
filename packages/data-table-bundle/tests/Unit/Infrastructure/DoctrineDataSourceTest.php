<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\DataTableBundle\Application\Exception\UnknownField;
use Phil\DataTableBundle\Application\Query\BooleanCriterion;
use Phil\DataTableBundle\Application\Query\ChoiceCriterion;
use Phil\DataTableBundle\Application\Query\DateRangeCriterion;
use Phil\DataTableBundle\Application\Query\ResultPage;
use Phil\DataTableBundle\Application\Query\Sort;
use Phil\DataTableBundle\Application\Query\SortDirection;
use Phil\DataTableBundle\Application\Query\TableQuery;
use Phil\DataTableBundle\Application\Query\TextCriterion;
use Phil\DataTableBundle\Infrastructure\Doctrine\DoctrineDataSource;
use Phil\DataTableBundle\Tests\Fixtures\Paper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctrineDataSource::class)]
#[CoversClass(UnknownField::class)]
final class DoctrineDataSourceTest extends TestCase
{
    private EntityManagerInterface $entities;

    #[Override]
    protected function setUp(): void
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2).'/Fixtures'], true);
        $configuration->enableNativeLazyObjects(true);

        $this->entities = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration), $configuration);
        new SchemaTool($this->entities)->createSchema([$this->entities->getClassMetadata(Paper::class)]);

        foreach (['Alpha 100%', 'Beta_x', 'gamma', 'delta!', 'epsilon'] as $position => $title) {
            $number = $position + 1;
            $this->entities->persist(new Paper($number, $title, 0 === $number % 2 ? 'draft' : 'live', 1 === $number % 2, new DateTimeImmutable('2026-01-0'.$number.' 12:00:00')));
        }

        $this->entities->flush();
        $this->entities->clear();
    }

    public function testAPageAndTheCount(): void
    {
        $page = $this->source()->fetch(new TableQuery([], [new Sort('id', SortDirection::Desc)], 2, 2));

        self::assertSame([[3, 2], 5], [$this->ids($page), $page->total]);
    }

    public function testSortsAreAppliedInOrder(): void
    {
        self::assertSame([2, 4, 1, 3, 5], $this->ids($this->source()->fetch(new TableQuery([], [new Sort('status'), new Sort('id')], 1, 10))));
        self::assertSame([4, 2, 5, 3, 1], $this->ids($this->source()->fetch(new TableQuery([], [new Sort('status'), new Sort('id', SortDirection::Desc)], 1, 10))));
    }

    public function testTextFiltersEscapeTheirWildcards(): void
    {
        self::assertSame([3], $this->filtered(new TextCriterion(['title'], 'GAMMA')));
        self::assertSame([1], $this->filtered(new TextCriterion(['title'], '0%')));
        self::assertSame([], $this->filtered(new TextCriterion(['title'], 'A%a')));
        self::assertSame([2], $this->filtered(new TextCriterion(['title'], '_')));
        self::assertSame([4], $this->filtered(new TextCriterion(['title'], '!')));
        self::assertSame([2, 4], $this->filtered(new TextCriterion(['title', 'status'], 'draft')), 'any of the fields');
    }

    public function testOtherFilters(): void
    {
        self::assertSame([2, 4], $this->filtered(new ChoiceCriterion('status', 'draft')));
        self::assertSame([1, 3, 5], $this->filtered(new BooleanCriterion('live', true)));
        self::assertSame([2, 4], $this->filtered(new BooleanCriterion('live', false)));
        self::assertSame([2, 3], $this->filtered(new DateRangeCriterion('at', new DateTimeImmutable('2026-01-02'), new DateTimeImmutable('2026-01-04'))));
        self::assertSame([2, 3, 4, 5], $this->filtered(new DateRangeCriterion('at', new DateTimeImmutable('2026-01-02 12:00:00'), null)), 'the start is included');
        self::assertSame([1, 2], $this->filtered(new DateRangeCriterion('at', null, new DateTimeImmutable('2026-01-03 00:00:00'))));
        self::assertSame([1, 2, 3, 4, 5], $this->filtered(new DateRangeCriterion('at', null, null)));
    }

    public function testFiltersAddUpAndLeaveTheBuilderAsItWas(): void
    {
        $builder = $this->builder();
        $source = new DoctrineDataSource($builder, $this->fields());
        $page = $source->fetch(new TableQuery([new ChoiceCriterion('status', 'draft'), new TextCriterion(['title'], 'delta')], [new Sort('id')], 1, 10));

        self::assertSame([4], $this->ids($page));
        self::assertNull($builder->getDQLPart('where'));
        self::assertSame([], $builder->getDQLPart('orderBy'));

        $source->find(['1']);
        self::assertNull($builder->getDQLPart('where'));
    }

    public function testAFieldOutsideTheAllowListStopsTheQuery(): void
    {
        $this->expectException(UnknownField::class);
        $this->expectExceptionMessageIsOrContains('The field "secret" is not among the fields this data source lists.');

        $this->source()->fetch(new TableQuery([], [new Sort('secret')], 1, 10));
    }

    public function testFindKeepsToTheBaseQuery(): void
    {
        $source = new DoctrineDataSource($this->builder()->andWhere("p.status = 'live'"), $this->fields());
        $rows = $source->find(['1', '2', '3']);

        self::assertSame([1, 3], array_map(static fn (object|array $row): int => $row instanceof Paper ? $row->id : 0, $rows));
    }

    public function testFindWithoutIdentifiersReadsNothing(): void
    {
        self::assertSame([], $this->source()->find([]));
        self::assertCount(2, $this->source()->find(['1', '2', '1']), 'A window as wide as the identifiers asked.');
    }

    public function testFindConvertsItsIdentifiersAndNamesItsField(): void
    {
        $source = new DoctrineDataSource($this->builder(), ['serial' => 'p.id'], 'serial', false, static fn (string $id): int => (int) $id * 2);
        $rows = $source->find(['2']);

        self::assertSame(4, $rows[0] instanceof Paper ? $rows[0]->id : 0);
    }

    public function testAFetchJoinedCollectionIsPagedByRoot(): void
    {
        $source = new DoctrineDataSource($this->builder(), $this->fields(), fetchJoinCollection: true);
        $page = $source->fetch(new TableQuery([], [new Sort('id')], 1, 2));

        self::assertSame([[1, 2], 5], [$this->ids($page), $page->total]);
        self::assertCount(1, $source->find(['1']));
    }

    /**
     * @return list<int>
     */
    private function filtered(TextCriterion|ChoiceCriterion|DateRangeCriterion|BooleanCriterion $criterion): array
    {
        return $this->ids($this->source()->fetch(new TableQuery([$criterion], [new Sort('id')], 1, 10)));
    }

    /**
     * @return list<int>
     */
    private function ids(ResultPage $page): array
    {
        return array_map(static fn (object|array $row): int => $row instanceof Paper ? $row->id : 0, $page->rows);
    }

    private function source(): DoctrineDataSource
    {
        return new DoctrineDataSource($this->builder(), $this->fields());
    }

    private function builder(): QueryBuilder
    {
        return $this->entities->createQueryBuilder()->select('p')->from(Paper::class, 'p');
    }

    /**
     * @return array<string, string>
     */
    private function fields(): array
    {
        return ['id' => 'p.id', 'title' => 'p.title', 'status' => 'p.status', 'live' => 'p.live', 'at' => 'p.at'];
    }
}
