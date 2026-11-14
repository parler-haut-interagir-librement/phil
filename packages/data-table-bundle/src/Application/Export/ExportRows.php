<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Export;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Generator;
use Phil\DataTableBundle\Application\Definition\Column;
use Phil\DataTableBundle\Application\Definition\ColumnType;
use Phil\DataTableBundle\Application\Definition\Export;
use Phil\DataTableBundle\Application\OpenTable;
use Phil\DataTableBundle\Application\Query\ResultPage;
use Phil\DataTableBundle\Application\Query\TableQuery;
use Phil\DataTableBundle\Application\RowValues;
use Phil\DataTableBundle\Application\State\ResolvedState;
use Phil\DataTableBundle\Application\State\StateResolver;
use Phil\DataTableBundle\Application\State\TableState;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The rows of a table as an export writes them: filtered and sorted as its state asks, every page of them, in its
 * visible columns but those drawn by a block. A number stays a number, a date a date in the column's zone, a boolean a
 * boolean; a choice is its translated label, a link its text. The rows are fetched from the data source a page at a
 * time while they are written, so memory holds one page whatever their number, and never more than the export allows.
 *
 * It knows no format: the bundle hands its rows to phil/data-table-export.
 */
final readonly class ExportRows
{
    /** The rows fetched at a time. */
    public const int PAGE = 500;

    public function __construct(
        private StateResolver $resolver,
        private RowValues $values,
        private TranslatorInterface $translator,
    ) {
    }

    public function rows(OpenTable $open, TableState $state): ExportedRows
    {
        $table = $open->definition;
        $resolved = $this->resolver->resolve($table, $state);
        $columns = $this->columns($open, $resolved);
        $query = new TableQuery($resolved->criteria, $resolved->sorts, 1, self::PAGE);
        $first = $open->source->fetch($query);
        $most = ($table->export ?? new Export())->maxRows;

        return new ExportedRows(
            $this->translator->trans($table->caption, [], $table->domain),
            array_map(fn (Column $column): string => $this->translator->trans($column->label, [], $table->domain), $columns),
            $first->total,
            $this->generate($open, $query, $first, $columns, $most),
        );
    }

    /**
     * @param list<Column> $columns
     *
     * @return Generator<int, list<mixed>>
     */
    private function generate(OpenTable $open, TableQuery $query, ResultPage $page, array $columns, int $most): Generator
    {
        $written = 0;
        $number = 1;

        while (true) {
            foreach ($page->rows as $row) {
                if ($written === $most) {
                    return;
                }

                ++$written;

                yield array_map(fn (Column $column): mixed => $this->cell($open, $column, $row), $columns);
            }

            if (\count($page->rows) < self::PAGE || $written === $most) {
                return;
            }

            $page = $open->source->fetch($query->onPage(++$number));
        }
    }

    /**
     * @return list<Column>
     */
    private function columns(OpenTable $open, ResolvedState $resolved): array
    {
        $columns = [] === $resolved->columns ? $open->definition->columns : array_intersect_key($open->definition->columns, array_flip($resolved->columns));

        return array_values(array_filter($columns, static fn (Column $column): bool => ColumnType::Block !== $column->type));
    }

    /**
     * @param array<string, mixed>|object $row
     */
    private function cell(OpenTable $open, Column $column, array|object $row): mixed
    {
        $value = $this->values->value($row, $column);

        return match (true) {
            null === $value => null,
            ColumnType::Number === $column->type && is_numeric($value) => +$value,
            ColumnType::Date === $column->type && $value instanceof DateTimeInterface => DateTimeImmutable::createFromInterface($value)->setTimezone(new DateTimeZone($column->timezone ?? date_default_timezone_get())),
            ColumnType::Enum === $column->type => $this->translator->trans($column->choices[$this->values->string($value)] ?? $this->values->string($value), [], $open->definition->domain),
            ColumnType::Boolean === $column->type => (bool) $value,
            default => $this->values->string($value),
        };
    }
}
