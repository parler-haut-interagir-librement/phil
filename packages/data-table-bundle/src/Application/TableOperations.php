<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application;

use Phil\DataTableBundle\Application\Contract\TableAccess;
use Phil\DataTableBundle\Application\Definition\Action;
use Phil\DataTableBundle\Application\Definition\Column;
use Phil\DataTableBundle\Application\Definition\Editing;
use Phil\DataTableBundle\Application\Definition\TableBuilder;
use Phil\DataTableBundle\Application\Exception\AccessDenied;
use Phil\DataTableBundle\Application\Exception\ActionRefused;
use Phil\DataTableBundle\Application\Exception\EditConflict;
use Phil\DataTableBundle\Application\Exception\InvalidEditValue;
use Phil\DataTableBundle\Application\Exception\NotFound;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The changes a table makes, each one checked at every call: the rows are read again from the table's own data source,
 * and the voter attribute is asked on each of them.
 */
final readonly class TableOperations
{
    public const string DOMAIN = 'phil_data_table';

    public function __construct(
        private TableAccess $access,
        private RowValues $values,
        private EditValues $editValues,
        private ValidatorInterface $validator,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * The action of this name and the rows it would act on, for the viewer.
     *
     * @param array<array-key, string> $ids
     *
     * @return array{Action, list<array<string, mixed>|object>}
     *
     * @throws NotFound     when the table has no such action for this many rows, or does not hold a row
     * @throws AccessDenied when a row refuses the viewer
     */
    public function authorize(OpenTable $table, string $name, array $ids): array
    {
        $definition = $table->definition;
        $ids = array_values(array_unique($ids));
        $action = $definition->actions[$name] ?? null;

        if (!$action instanceof Action || [] === $ids || \count($ids) > TableBuilder::MAX_PAGE_SIZE || !$action->accepts(\count($ids))) {
            throw NotFound::action($definition->name, $name);
        }

        $rows = $table->source->find($ids);

        if (\count($rows) !== \count($ids)) {
            throw NotFound::rows($definition->name);
        }

        foreach ($rows as $row) {
            if (!$this->access->isGranted($action->attribute, $row)) {
                throw AccessDenied::to(\sprintf('the action "%s"', $name), $action->attribute);
            }
        }

        return [$action, $rows];
    }

    /**
     * @param array<array-key, string> $ids
     *
     * @throws NotFound      when the table has no such action for this many rows, or does not hold a row
     * @throws AccessDenied  when a row refuses the viewer
     * @throws ActionRefused when the action cannot go through
     */
    public function perform(OpenTable $table, string $name, array $ids): void
    {
        [$action, $rows] = $this->authorize($table, $name, $ids);

        ($action->handler)($rows);
    }

    /**
     * What an edit of this cell starts from.
     *
     * @throws NotFound     when the column is not editable or the table does not hold the row
     * @throws AccessDenied when the row refuses the viewer
     */
    public function start(OpenTable $table, string $id, string $columnName): EditStart
    {
        [$column, , $row] = $this->cell($table, $id, $columnName);

        return new EditStart($this->editValues->raw($column, $this->values->value($row, $column)), $this->version($table, $row));
    }

    /**
     * Writes a cell: the access first, then the version, the value's type and the column's constraints.
     *
     * @throws NotFound     when the column is not editable or the table does not hold the row
     * @throws AccessDenied when the row refuses the viewer
     */
    public function edit(OpenTable $table, string $id, string $columnName, string $raw, ?string $version): EditOutcome
    {
        [$column, $editing, $row] = $this->cell($table, $id, $columnName);
        $current = $this->version($table, $row);

        if (null !== $current && !hash_equals($current, (string) $version)) {
            return $this->conflict();
        }

        try {
            $value = $this->editValues->convert($column, $raw);
        } catch (InvalidEditValue) {
            return new EditOutcome(EditStatus::Invalid, [$this->translator->trans('table.edit.invalid', [], self::DOMAIN)]);
        }

        $errors = [];

        foreach ($this->validator->validate($value, $editing->constraints) as $violation) {
            $errors[] = (string) $violation->getMessage();
        }

        if ([] !== $errors) {
            return new EditOutcome(EditStatus::Invalid, $errors);
        }

        try {
            ($editing->apply)($row, $value);
        } catch (EditConflict) {
            return $this->conflict();
        } catch (ActionRefused $refused) {
            return new EditOutcome(EditStatus::Invalid, [$this->translator->trans($refused->getMessage(), [], $table->definition->domain)]);
        }

        return new EditOutcome(EditStatus::Saved);
    }

    /**
     * @return array{Column, Editing, array<string, mixed>|object}
     */
    private function cell(OpenTable $table, string $id, string $columnName): array
    {
        $definition = $table->definition;
        $column = $definition->columns[$columnName] ?? null;
        $editing = $column?->editing;

        if (!$column instanceof Column || !$editing instanceof Editing) {
            throw NotFound::editableColumn($definition->name, $columnName);
        }

        $rows = $table->source->find([$id]);

        if ([] === $rows) {
            throw NotFound::rows($definition->name);
        }

        if (!$this->access->isGranted($editing->attribute, $rows[0])) {
            throw AccessDenied::to(\sprintf('the column "%s"', $columnName), $editing->attribute);
        }

        return [$column, $editing, $rows[0]];
    }

    /**
     * @param array<string, mixed>|object $row
     */
    private function version(OpenTable $table, array|object $row): ?string
    {
        $version = $table->definition->version;

        return null === $version ? null : $this->values->string($this->values->read($row, $version));
    }

    private function conflict(): EditOutcome
    {
        return new EditOutcome(EditStatus::Conflict, [$this->translator->trans('table.edit.conflict', [], self::DOMAIN)]);
    }
}
