<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Infrastructure\Doctrine;

use Closure;
use DateTimeImmutable;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\OffsetPaginator;
use Doctrine\ORM\Tools\Pagination\Window;
use Override;
use Phil\DataTableBundle\Application\Contract\DataSource;
use Phil\DataTableBundle\Application\Exception\UnknownField;
use Phil\DataTableBundle\Application\Query\BooleanCriterion;
use Phil\DataTableBundle\Application\Query\ChoiceCriterion;
use Phil\DataTableBundle\Application\Query\DateRangeCriterion;
use Phil\DataTableBundle\Application\Query\ResultPage;
use Phil\DataTableBundle\Application\Query\SortDirection;
use Phil\DataTableBundle\Application\Query\TableQuery;
use Phil\DataTableBundle\Application\Query\TextCriterion;

/**
 * A table read from a QueryBuilder. `$fields` is the allow-list: only its keys reach the query, as the DQL expressions
 * they map to, and every value is a bound parameter. The builder given is never changed: each call works on a copy.
 * A page is read through the ORM's `OffsetPaginator`, its window given apart from the query.
 */
final readonly class DoctrineDataSource implements DataSource
{
    /**
     * @param array<string, string>         $fields              field name to DQL expression, the identifier among them
     * @param bool                          $fetchJoinCollection whether the query fetch-joins a collection
     * @param (Closure(string): mixed)|null $toIdentifier        what an identifier is bound as, a `Uuid` for instance; the string itself when null
     */
    public function __construct(
        private QueryBuilder $queryBuilder,
        private array $fields,
        private string $identifier = 'id',
        private bool $fetchJoinCollection = false,
        private ?Closure $toIdentifier = null,
    ) {
    }

    #[Override]
    public function fetch(TableQuery $query): ResultPage
    {
        $builder = clone $this->queryBuilder;

        foreach ($query->filters as $position => $criterion) {
            $this->apply($builder, $criterion, 'phil_dt_'.$position);
        }

        foreach ($query->sorts as $sort) {
            $builder->addOrderBy($this->field($sort->field), SortDirection::Desc === $sort->direction ? 'DESC' : 'ASC');
        }

        $page = new OffsetPaginator($this->fetchJoinCollection)->paginate($builder->getQuery(), new Window(max(0, $query->offset()), max(1, $query->pageSize)));
        /** @var list<array<string, mixed>|object> $rows */
        $rows = $page->getItems();

        return new ResultPage($rows, $page->getTotalCount());
    }

    #[Override]
    public function find(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $builder = clone $this->queryBuilder;
        $builder->andWhere($builder->expr()->in($this->field($this->identifier), ':phil_dt_ids'))
            ->setParameter('phil_dt_ids', $this->toIdentifier instanceof Closure ? array_map($this->toIdentifier, $ids) : $ids);
        /** @var list<array<string, mixed>|object> $rows */
        $rows = new OffsetPaginator($this->fetchJoinCollection)->paginate($builder->getQuery(), new Window(0, \count($ids)))->getItems();

        return $rows;
    }

    private function apply(QueryBuilder $builder, TextCriterion|ChoiceCriterion|DateRangeCriterion|BooleanCriterion $criterion, string $parameter): void
    {
        if ($criterion instanceof TextCriterion) {
            $matches = array_map(fn (string $field): string => \sprintf("LOWER(%s) LIKE LOWER(:%s) ESCAPE '!'", $this->field($field), $parameter), $criterion->fields);
            $builder->andWhere($builder->expr()->orX(...$matches))
                ->setParameter($parameter, '%'.strtr($criterion->text, ['!' => '!!', '%' => '!%', '_' => '!_']).'%');

            return;
        }

        $field = $this->field($criterion->field);

        if ($criterion instanceof DateRangeCriterion) {
            if ($criterion->from instanceof DateTimeImmutable) {
                $builder->andWhere(\sprintf('%s >= :%s_from', $field, $parameter))->setParameter($parameter.'_from', $criterion->from);
            }

            if ($criterion->before instanceof DateTimeImmutable) {
                $builder->andWhere(\sprintf('%s < :%s_before', $field, $parameter))->setParameter($parameter.'_before', $criterion->before);
            }

            return;
        }

        $builder->andWhere(\sprintf('%s = :%s', $field, $parameter))->setParameter($parameter, $criterion->value);
    }

    private function field(string $name): string
    {
        return $this->fields[$name] ?? throw UnknownField::named($name);
    }
}
