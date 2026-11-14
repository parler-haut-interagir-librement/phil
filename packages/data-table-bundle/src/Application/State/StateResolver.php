<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\State;

use DateTimeImmutable;
use DateTimeZone;
use Phil\DataTableBundle\Application\Definition\Column;
use Phil\DataTableBundle\Application\Definition\Filter;
use Phil\DataTableBundle\Application\Definition\FilterType;
use Phil\DataTableBundle\Application\Definition\TableBuilder;
use Phil\DataTableBundle\Application\Definition\TableDefinition;
use Phil\DataTableBundle\Application\Query\BooleanCriterion;
use Phil\DataTableBundle\Application\Query\ChoiceCriterion;
use Phil\DataTableBundle\Application\Query\DateRangeCriterion;
use Phil\DataTableBundle\Application\Query\Sort;
use Phil\DataTableBundle\Application\Query\SortDirection;
use Phil\DataTableBundle\Application\Query\TextCriterion;

/**
 * Checks what the address says against the table's declaration: only a declared column sorts, only a declared filter
 * filters, a value outside its filter's choices is dropped, a page stays within the furthest offset, and a page size
 * is one of those offered. The address never reaches a query as it was written.
 */
final readonly class StateResolver
{
    /** The longest text a text filter looks for. */
    public const int MAX_TEXT = 200;

    public function resolve(TableDefinition $table, TableState $state): ResolvedState
    {
        $asked = $this->askedSort($table, $state->sort);
        $sorts = $asked instanceof Sort ? [$asked] : $table->sorts;
        $pageSize = \in_array($state->size, $table->pageSizes, true) ? $state->size : $table->pageSizes[0];
        [$filters, $criteria] = $this->filters($table, $state->filters);

        return new ResolvedState(
            $asked instanceof Sort ? $state->sort : '',
            $sorts[0] ?? null,
            $this->fieldSorts($table, $sorts),
            min(max($state->page, 1), self::lastReachablePage($table, $pageSize)),
            $pageSize,
            $filters,
            $criteria,
            $this->columns($table, $state->columns),
            $this->selected($state->selected),
        );
    }

    /**
     * The last page a table reaches at this page size, whatever the number of rows.
     */
    public static function lastReachablePage(TableDefinition $table, int $pageSize): int
    {
        return (int) floor($table->maxOffset / $pageSize) + 1;
    }

    private function askedSort(TableDefinition $table, string $sort): ?Sort
    {
        $descending = str_starts_with($sort, '-');
        $name = $descending ? substr($sort, 1) : $sort;

        if (null === ($table->columns[$name] ?? null)?->sortField) {
            return null;
        }

        return new Sort($name, $descending ? SortDirection::Desc : SortDirection::Asc);
    }

    /**
     * The sorts by the fields of the data source, ending on the identifier: rows that tie keep their place from one
     * page to the next.
     *
     * @param list<Sort> $sorts by column names
     *
     * @return list<Sort>
     */
    private function fieldSorts(TableDefinition $table, array $sorts): array
    {
        $fields = [];

        foreach ($sorts as $sort) {
            /** @var Column $column */
            $column = $table->columns[$sort->field];
            $field = (string) $column->sortField;
            $fields[$field] = new Sort($field, $sort->direction);
        }

        $fields[$table->identifier] ??= new Sort($table->identifier);

        return array_values($fields);
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array{array<string, string>, list<TextCriterion|ChoiceCriterion|DateRangeCriterion|BooleanCriterion>}
     */
    private function filters(TableDefinition $table, array $values): array
    {
        $kept = [];
        $criteria = [];

        foreach ($table->filters as $filter) {
            if (FilterType::DateRange === $filter->type) {
                [$range, $days] = $this->dateRange($filter, $values);
                $kept += $days;
                $criteria = [...$criteria, ...$range];

                continue;
            }

            $value = $this->value($filter, $values[$filter->name] ?? '');

            if (null !== $value) {
                $kept[$filter->name] = $value;
                $criteria[] = match ($filter->type) {
                    FilterType::Text => new TextCriterion($filter->fields, $value),
                    FilterType::Enum => new ChoiceCriterion($filter->fields[0], $value),
                    default => new BooleanCriterion($filter->fields[0], '1' === $value),
                };
            }
        }

        return [$kept, $criteria];
    }

    private function value(Filter $filter, mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        return match ($filter->type) {
            FilterType::Text => '' === ($text = mb_substr(trim($value), 0, self::MAX_TEXT)) ? null : $text,
            FilterType::Enum => isset($filter->choices[$value]) ? $value : null,
            default => '1' === $value || '0' === $value ? $value : null,
        };
    }

    /**
     * The days read and what they ask; days in the wrong order are swapped.
     *
     * @param array<array-key, mixed> $values
     *
     * @return array{list<DateRangeCriterion>, array<string, string>}
     */
    private function dateRange(Filter $filter, array $values): array
    {
        $zone = new DateTimeZone($filter->timezone ?? date_default_timezone_get());
        $from = $this->day($values[$filter->name.'_from'] ?? '', $zone);
        $until = $this->day($values[$filter->name.'_until'] ?? '', $zone);

        if (!$from instanceof DateTimeImmutable && !$until instanceof DateTimeImmutable) {
            return [[], []];
        }

        if ($from instanceof DateTimeImmutable && $until instanceof DateTimeImmutable && $from > $until) {
            [$from, $until] = [$until, $from];
        }

        $days = [];

        if ($from instanceof DateTimeImmutable) {
            $days[$filter->name.'_from'] = $from->format('Y-m-d');
        }

        if ($until instanceof DateTimeImmutable) {
            $days[$filter->name.'_until'] = $until->format('Y-m-d');
        }

        return [[new DateRangeCriterion($filter->fields[0], $from, $until?->modify('+1 day'))], $days];
    }

    private function day(mixed $value, DateTimeZone $zone): ?DateTimeImmutable
    {
        if (!\is_string($value)) {
            return null;
        }

        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $zone);

        return false !== $day && $day->format('Y-m-d') === $value ? $day : null;
    }

    /**
     * The visible columns when some are hidden, in the table's order; an empty list when all are shown.
     *
     * @param array<array-key, mixed> $columns
     *
     * @return list<string>
     */
    private function columns(TableDefinition $table, array $columns): array
    {
        $visible = array_values(array_filter(array_keys($table->columns), static fn (string $name): bool => \in_array($name, $columns, true)));

        return \count($visible) === \count($table->columns) ? [] : $visible;
    }

    /**
     * @param array<array-key, mixed> $selected
     *
     * @return list<string>
     */
    private function selected(array $selected): array
    {
        $ids = array_values(array_unique(array_filter($selected, \is_string(...))));

        return \array_slice($ids, 0, TableBuilder::MAX_PAGE_SIZE);
    }
}
