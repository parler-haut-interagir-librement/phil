<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\View;

use Closure;
use DateTimeInterface;
use Phil\DataTableBundle\Application\Contract\TableAccess;
use Phil\DataTableBundle\Application\Definition\Action;
use Phil\DataTableBundle\Application\Definition\Column;
use Phil\DataTableBundle\Application\Definition\ColumnType;
use Phil\DataTableBundle\Application\Definition\Editing;
use Phil\DataTableBundle\Application\Definition\TableDefinition;
use Phil\DataTableBundle\Application\EditValues;
use Phil\DataTableBundle\Application\RowValues;
use Phil\DataTableBundle\Application\State\ResolvedState;
use Phil\DataTableBundle\Application\State\TableLinks;
use Phil\DataTableBundle\Application\State\TableState;
use Phil\DataTableBundle\Application\TableOperations;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Lays out the rows of one render, and lives only for it.
 */
final class RowRenderer
{
    /**
     * A link keeps to these: http, https, mailto, tel, an address from the root of this site, a query, a fragment, or
     * a relative path. Anything else, `javascript:` first, is written as text.
     */
    private const string SAFE_HREF = '~^(?:(?:https?|mailto|tel):|/(?![/\\\\])|[?#]|[^:/?#\\\\]+(?:[/?#]|$))~i';

    /** @var list<Column> */
    private readonly array $columns;

    /** @var array<string, string> */
    private array $labels = [];

    /** @var array<string, array<string, string>> */
    private array $choices = [];

    private readonly string $yes;

    private readonly string $no;

    public function __construct(
        private readonly TableDefinition $table,
        private readonly TableState $state,
        private readonly ResolvedState $resolved,
        private readonly TableLinks $links,
        private readonly TableAccess $access,
        private readonly RowValues $values,
        private readonly EditValues $editValues,
        private readonly TranslatorInterface $translator,
        private readonly Formats $formats,
    ) {
        $this->columns = array_values(array_filter($table->columns, static fn (Column $column): bool => [] === $resolved->columns || \in_array($column->name, $resolved->columns, true)));

        foreach ($this->columns as $column) {
            $this->labels[$column->name] = $this->translator->trans($column->label, [], $this->table->domain);
        }

        $this->yes = $this->translator->trans('table.yes', [], TableOperations::DOMAIN);
        $this->no = $this->translator->trans('table.no', [], TableOperations::DOMAIN);
    }

    /**
     * @return list<Column>
     */
    public function columns(): array
    {
        return $this->columns;
    }

    public function label(Column $column): string
    {
        return $this->labels[$column->name];
    }

    /**
     * @param array<string, mixed>|object $row
     */
    public function row(array|object $row): RowView
    {
        $id = $this->values->string($this->values->read($row, $this->table->identifier));
        $cells = [];

        foreach ($this->columns as $position => $column) {
            $cells[] = $this->cell($row, $id, $column, 0 === $position);
        }

        return new RowView($id, $cells[0]->text, $cells, $this->actions($row, $id), \in_array($id, $this->resolved->selected, true));
    }

    /**
     * @param array<string, mixed>|object $row
     */
    private function cell(array|object $row, string $id, Column $column, bool $header): CellView
    {
        $value = $this->values->value($row, $column);
        $editable = $column->editing instanceof Editing && $this->access->isGranted($column->editing->attribute, $row);
        $editing = $editable && $this->state->edit === $column->name && $this->state->row === $id;

        return new CellView(
            $column->name,
            $this->labels[$column->name],
            $column->type->value,
            $header,
            $this->text($column, $value),
            $value,
            $row,
            $this->href($row, $column),
            $column->template,
            $column->block,
            $editable ? $this->links->href(['edit' => $column->name, 'row' => $id]) : null,
            $editing ? $this->edit($row, $column, $value) : null,
        );
    }

    private function text(Column $column, mixed $value): string
    {
        return match (true) {
            null === $value => '',
            ColumnType::Number === $column->type && is_numeric($value) => $this->formats->number($column, $value),
            ColumnType::Date === $column->type && $value instanceof DateTimeInterface => $this->formats->date($column, $value),
            ColumnType::Enum === $column->type => $this->choice($column, $this->values->string($value)),
            ColumnType::Boolean === $column->type => (bool) $value ? $this->yes : $this->no,
            default => $this->values->string($value),
        };
    }

    private function choice(Column $column, string $value): string
    {
        return $this->choices[$column->name][$value] ??= $this->translator->trans($column->choices[$value] ?? $value, [], $this->table->domain);
    }

    /**
     * @param array<string, mixed>|object $row
     */
    private function href(array|object $row, Column $column): ?string
    {
        if (!$column->href instanceof Closure) {
            return null;
        }

        $href = ($column->href)($row);

        return null !== $href && 1 === preg_match(self::SAFE_HREF, $href) ? $href : null;
    }

    /**
     * @param array<string, mixed>|object $row
     */
    private function edit(array|object $row, Column $column, mixed $value): EditView
    {
        $choices = [];

        if (ColumnType::Enum === $column->type) {
            $choices[] = new ChoiceView('', '');

            foreach ($column->choices as $choice => $label) {
                $choices[] = new ChoiceView((string) $choice, $this->choice($column, (string) $choice));
            }
        } elseif (ColumnType::Boolean === $column->type) {
            $choices = [new ChoiceView('1', $this->yes), new ChoiceView('0', $this->no)];
        }

        return new EditView(
            match ($column->type) {
                ColumnType::Number => 'number',
                ColumnType::Date => 'date',
                ColumnType::Enum, ColumnType::Boolean => 'select',
                default => 'text',
            },
            $this->state->draft ?? $this->editValues->raw($column, $value),
            $choices,
            null === $this->table->version ? null : $this->values->string($this->values->read($row, $this->table->version)),
            $this->state->errors,
            $this->links->href(),
        );
    }

    /**
     * @param array<string, mixed>|object $row
     *
     * @return list<ActionView>
     */
    private function actions(array|object $row, string $id): array
    {
        $views = [];

        foreach ($this->table->actions as $action) {
            if ($action->onRow && $this->access->isGranted($action->attribute, $row)) {
                $views[] = $this->action($action, $this->links->href(['confirm' => $action->name, 'row' => $id]));
            }
        }

        return $views;
    }

    public function action(Action $action, ?string $href = null): ActionView
    {
        return new ActionView($action->name, $this->translator->trans($action->label, [], $this->table->domain), $action->destructive, $href);
    }
}
