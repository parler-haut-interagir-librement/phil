<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Source;

use BackedEnum;
use DateTimeInterface;
use Override;
use Phil\DataTableBundle\Application\Contract\DataSource;
use Phil\DataTableBundle\Application\Query\BooleanCriterion;
use Phil\DataTableBundle\Application\Query\ChoiceCriterion;
use Phil\DataTableBundle\Application\Query\DateRangeCriterion;
use Phil\DataTableBundle\Application\Query\ResultPage;
use Phil\DataTableBundle\Application\Query\SortDirection;
use Phil\DataTableBundle\Application\Query\TableQuery;
use Phil\DataTableBundle\Application\Query\TextCriterion;
use Phil\DataTableBundle\Application\RowValues;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * A table over rows already in memory: a short list, a table of a test. Fields are array keys or property paths.
 */
final readonly class InMemoryDataSource implements DataSource
{
    private RowValues $values;

    /**
     * @param list<array<string, mixed>|object> $rows
     */
    public function __construct(
        private array $rows,
        private string $identifier = 'id',
    ) {
        $this->values = new RowValues(PropertyAccess::createPropertyAccessor());
    }

    #[Override]
    public function fetch(TableQuery $query): ResultPage
    {
        $rows = array_filter($this->rows, fn (array|object $row): bool => array_all($query->filters, fn (TextCriterion|ChoiceCriterion|DateRangeCriterion|BooleanCriterion $criterion): bool => $this->matches($row, $criterion)));

        usort($rows, function (array|object $left, array|object $right) use ($query): int {
            foreach ($query->sorts as $sort) {
                $order = $this->comparable($this->values->read($left, $sort->field)) <=> $this->comparable($this->values->read($right, $sort->field));

                if (0 !== $order) {
                    return SortDirection::Desc === $sort->direction ? -$order : $order;
                }
            }

            return 0;
        });

        return new ResultPage(\array_slice($rows, $query->offset(), $query->pageSize), \count($rows));
    }

    #[Override]
    public function find(array $ids): array
    {
        return array_values(array_filter($this->rows, fn (array|object $row): bool => \in_array($this->values->string($this->values->read($row, $this->identifier)), $ids, true)));
    }

    /**
     * @param array<string, mixed>|object $row
     */
    private function matches(array|object $row, TextCriterion|ChoiceCriterion|DateRangeCriterion|BooleanCriterion $criterion): bool
    {
        if ($criterion instanceof TextCriterion) {
            return array_any($criterion->fields, fn (string $field): bool => false !== mb_stripos($this->values->string($this->values->read($row, $field)), $criterion->text));
        }

        $value = $this->values->read($row, $criterion->field);

        if ($criterion instanceof ChoiceCriterion) {
            return $this->values->string($value) === $criterion->value;
        }

        if ($criterion instanceof BooleanCriterion) {
            return (bool) $value === $criterion->value;
        }

        return $value instanceof DateTimeInterface
            && (!$criterion->from instanceof DateTimeInterface || $value >= $criterion->from)
            && (!$criterion->before instanceof DateTimeInterface || $value < $criterion->before);
    }

    private function comparable(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
