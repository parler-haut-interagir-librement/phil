<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\View;

use Phil\DataTableBundle\Application\Contract\TableAccess;
use Phil\DataTableBundle\Application\Definition\Filter;
use Phil\DataTableBundle\Application\Definition\FilterType;
use Phil\DataTableBundle\Application\Definition\TableDefinition;
use Phil\DataTableBundle\Application\EditValues;
use Phil\DataTableBundle\Application\Exception\AccessDenied;
use Phil\DataTableBundle\Application\Exception\NotFound;
use Phil\DataTableBundle\Application\OpenTable;
use Phil\DataTableBundle\Application\Query\ResultPage;
use Phil\DataTableBundle\Application\Query\SortDirection;
use Phil\DataTableBundle\Application\Query\TableQuery;
use Phil\DataTableBundle\Application\RowValues;
use Phil\DataTableBundle\Application\State\ResolvedState;
use Phil\DataTableBundle\Application\State\StateResolver;
use Phil\DataTableBundle\Application\State\TableLinks;
use Phil\DataTableBundle\Application\State\TableState;
use Phil\DataTableBundle\Application\TableOperations;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Lays a table out for a template: the query its state asks, the page that comes back, and every label translated and
 * value formatted. The service keeps nothing between two calls.
 */
final readonly class TableViews
{
    /** How many page numbers are shown on each side of the current one. */
    private const int PAGE_WINDOW = 2;

    public function __construct(
        private StateResolver $resolver,
        private TableAccess $access,
        private RowValues $values,
        private EditValues $editValues,
        private TableOperations $operations,
        private TranslatorInterface $translator,
    ) {
    }

    public function view(OpenTable $open, TableState $state): TableView
    {
        $table = $open->definition;
        $resolved = $this->resolver->resolve($table, $state);
        [$result, $page, $lastPage] = $this->fetch($open, $resolved);
        $links = new TableLinks($state->urlPrefix, $state->preserved, [
            'sort' => $resolved->sort,
            'size' => $resolved->pageSize === $table->pageSizes[0] ? null : $resolved->pageSize,
            'filters' => $resolved->filters,
            'columns' => $resolved->columns,
        ]);
        $renderer = new RowRenderer($table, $state, $resolved, $links, $this->access, $this->values, $this->editValues, $this->translator, new Formats($this->translator->getLocale()));
        $rows = array_map($renderer->row(...), $result->rows);

        return new TableView(
            $table->name,
            $state->urlPrefix,
            'phil-data-table-'.$state->urlPrefix.$table->name,
            $this->translator->trans($table->caption, [], $table->domain),
            $this->headers($resolved, $renderer),
            $rows,
            array_map(fn (Filter $filter): FilterView => $this->filter($table, $filter, $resolved), array_values($table->filters)),
            $this->columnOptions($table, $resolved),
            $this->pagination($resolved, $links, $result, $page, $lastPage),
            $table->pageSizes,
            $resolved->pageSize,
            $this->bulkActions($table, $renderer, $result),
            $this->confirmation($open, $state, $resolved, $links),
            $result->total,
            $links->hiddenFields(['sort']),
            array_any($rows, static fn (RowView $row): bool => [] !== $row->actions),
            [] !== $resolved->filters,
            $links->href(['filters' => []]),
            $links->href(['page' => $page]),
            '' !== $state->edit && '' !== $state->row && !array_any($rows, static fn (RowView $row): bool => $row->id === $state->row),
        );
    }

    /**
     * The page asked for, or the last one when it no longer exists.
     *
     * @return array{ResultPage, int, int}
     */
    private function fetch(OpenTable $open, ResolvedState $resolved): array
    {
        $query = new TableQuery($resolved->criteria, $resolved->sorts, $resolved->page, $resolved->pageSize);
        $result = $open->source->fetch($query);
        $lastPage = max(1, min((int) ceil($result->total / $resolved->pageSize), StateResolver::lastReachablePage($open->definition, $resolved->pageSize)));

        if ($resolved->page <= $lastPage) {
            return [$result, $resolved->page, $lastPage];
        }

        return [$open->source->fetch($query->onPage($lastPage)), $lastPage, $lastPage];
    }

    /**
     * @return list<HeaderView>
     */
    private function headers(ResolvedState $resolved, RowRenderer $renderer): array
    {
        $headers = [];
        $active = $resolved->active;

        foreach ($renderer->columns() as $column) {
            $sorted = $active?->field === $column->name;
            $ascending = $sorted && SortDirection::Asc === $active->direction;
            $headers[] = new HeaderView(
                $column->name,
                $renderer->label($column),
                $sorted ? ($ascending ? 'ascending' : 'descending') : null,
                null === $column->sortField ? null : ($ascending ? '-' : '').$column->name,
            );
        }

        return $headers;
    }

    private function filter(TableDefinition $table, Filter $filter, ResolvedState $resolved): FilterView
    {
        $choices = [];

        if (FilterType::Boolean === $filter->type) {
            $choices = [new ChoiceView('1', $this->translator->trans('table.yes', [], TableOperations::DOMAIN)), new ChoiceView('0', $this->translator->trans('table.no', [], TableOperations::DOMAIN))];
        }

        foreach ($filter->choices as $value => $label) {
            $choices[] = new ChoiceView((string) $value, $this->translator->trans($label, [], $table->domain));
        }

        return new FilterView(
            $filter->name,
            $filter->type->value,
            $this->translator->trans($filter->label, [], $table->domain),
            $resolved->filters[$filter->name] ?? '',
            $resolved->filters[$filter->name.'_from'] ?? '',
            $resolved->filters[$filter->name.'_until'] ?? '',
            $choices,
        );
    }

    /**
     * @return list<ColumnOptionView>
     */
    private function columnOptions(TableDefinition $table, ResolvedState $resolved): array
    {
        $options = [];

        foreach ($table->columns as $column) {
            $options[] = new ColumnOptionView($column->name, $this->translator->trans($column->label, [], $table->domain), [] === $resolved->columns || \in_array($column->name, $resolved->columns, true));
        }

        return $options;
    }

    private function pagination(ResolvedState $resolved, TableLinks $links, ResultPage $result, int $page, int $lastPage): PaginationView
    {
        $pageLinks = [];
        $previous = 0;

        foreach (range(1, $lastPage) as $number) {
            if (1 !== $number && $number !== $lastPage && abs($number - $page) > self::PAGE_WINDOW) {
                continue;
            }

            if ($number > $previous + 1) {
                $pageLinks[] = null;
            }

            $pageLinks[] = new PageLink($number, $links->href(['page' => $number]), $number === $page);
            $previous = $number;
        }

        $first = [] === $result->rows ? 0 : ($page - 1) * $resolved->pageSize + 1;

        return new PaginationView(
            $page,
            $lastPage,
            $first,
            [] === $result->rows ? 0 : $first + \count($result->rows) - 1,
            $page > 1 ? $links->href(['page' => $page - 1]) : null,
            $page < $lastPage ? $links->href(['page' => $page + 1]) : null,
            $pageLinks,
            $result->total > $lastPage * $resolved->pageSize,
        );
    }

    /**
     * The actions at least one row of the page allows.
     *
     * @return list<ActionView>
     */
    private function bulkActions(TableDefinition $table, RowRenderer $renderer, ResultPage $result): array
    {
        $views = [];

        foreach ($table->actions as $action) {
            if ($action->onSelection && array_any($result->rows, fn (array|object $row): bool => $this->access->isGranted($action->attribute, $row))) {
                $views[] = $renderer->action($action);
            }
        }

        return $views;
    }

    /**
     * The question of the action the state asks about, when the viewer may run it on these rows.
     */
    private function confirmation(OpenTable $open, TableState $state, ResolvedState $resolved, TableLinks $links): ?ConfirmationView
    {
        $ids = '' === $state->row ? $resolved->selected : [$state->row];

        try {
            [$action] = $this->operations->authorize($open, $state->confirm, $ids);
        } catch (NotFound|AccessDenied) {
            return null;
        }

        $domain = $open->definition->domain;
        $label = $this->translator->trans($action->label, [], $domain);

        return new ConfirmationView(
            $action->name,
            $label,
            null === $action->confirmation
                ? $this->translator->trans('table.confirm.question', ['%count%' => \count($ids), '%action%' => $label], TableOperations::DOMAIN)
                : $this->translator->trans($action->confirmation, ['%count%' => \count($ids)], $domain),
            $ids,
            $action->destructive,
            $links->href(),
        );
    }
}
