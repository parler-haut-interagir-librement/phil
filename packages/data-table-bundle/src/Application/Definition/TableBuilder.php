<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Definition;

use Phil\DataTableBundle\Application\Exception\InvalidDefinition;
use Phil\DataTableBundle\Application\Query\Sort;
use Phil\DataTableBundle\Application\Query\SortDirection;

/**
 * Declares a table, in `DataTable::configure()`.
 */
final class TableBuilder
{
    /** The largest page a table can offer. */
    public const int MAX_PAGE_SIZE = 500;

    private const string NAME = '/^[A-Za-z][A-Za-z0-9_]{0,63}$/D';

    private string $caption = '';

    private string $domain = 'messages';

    private string $identifier = 'id';

    private ?string $version = null;

    private ?string $attribute = null;

    /** @var array<string, Column> */
    private array $columns = [];

    /** @var array<string, Filter> */
    private array $filters = [];

    /** @var array<string, Action> */
    private array $actions = [];

    /** @var list<Sort> */
    private array $sorts = [];

    /** @var list<int> */
    private array $pageSizes = [25, 50, 100];

    private int $maxOffset = 10_000;

    private ?Export $export = null;

    public function __construct(
        private readonly string $name,
    ) {
    }

    /**
     * The table's `<caption>`, required: it is what names the table to a screen reader.
     */
    public function caption(string $caption): self
    {
        $this->caption = $caption;

        return $this;
    }

    /**
     * The translation domain of the labels, `messages` when not called.
     */
    public function translationDomain(string $domain): self
    {
        $this->domain = $domain;

        return $this;
    }

    /**
     * The row's property that identifies it, `id` when not called; also a field of the data source.
     */
    public function identifier(string $property): self
    {
        $this->identifier = $property;

        return $this;
    }

    /**
     * The row's property that changes with each write, for the conflicts of inline editing.
     */
    public function version(string $property): self
    {
        $this->version = $property;

        return $this;
    }

    /**
     * The voter attribute a viewer needs to see the table, everyone when not called.
     */
    public function requires(string $attribute): self
    {
        $this->attribute = $attribute;

        return $this;
    }

    public function column(Column $column): self
    {
        $this->check('column', $column->name, $this->columns);
        $this->columns[$column->name] = $column;

        return $this;
    }

    public function filter(Filter $filter): self
    {
        $this->check('filter', $filter->name, $this->filters);
        $this->filters[$filter->name] = $filter;

        return $this;
    }

    public function action(Action $action): self
    {
        $this->check('action', $action->name, $this->actions);
        $this->actions[$action->name] = $action;

        return $this;
    }

    /**
     * Adds a sort by default, on a sortable column.
     */
    public function sortBy(string $column, SortDirection $direction = SortDirection::Asc): self
    {
        $this->sorts[] = new Sort($column, $direction);

        return $this;
    }

    /**
     * The page sizes offered, the first by default.
     */
    public function pageSizes(int ...$sizes): self
    {
        $this->pageSizes = array_values($sizes);

        return $this;
    }

    /**
     * The furthest row a page starts from: the cost of an offset grows with it.
     */
    public function maxOffset(int $maxOffset): self
    {
        $this->maxOffset = $maxOffset;

        return $this;
    }

    /**
     * Offers the export of the table, filtered and sorted as it is shown, its visible columns, to whoever may see it
     * and holds the attribute, when one is given; used when phil/data-table-export is installed.
     *
     * @param int $maxRows the most rows exported: past them, the export is refused
     */
    public function exportable(?string $attribute = null, int $maxRows = Export::MAX_ROWS): self
    {
        $this->export = new Export($attribute, $maxRows);

        return $this;
    }

    /**
     * @throws InvalidDefinition when the table cannot be drawn as declared
     */
    public function build(): TableDefinition
    {
        $this->assert('' !== $this->caption, 'a caption is required.');
        $this->assert([] !== $this->columns, 'a column is required.');
        $this->assert([] !== $this->pageSizes, 'a page size is required.');
        $this->assert([] === array_filter($this->pageSizes, static fn (int $size): bool => $size < 1 || $size > self::MAX_PAGE_SIZE), \sprintf('a page size is between 1 and %d.', self::MAX_PAGE_SIZE));
        $this->assert($this->maxOffset >= 0, 'the furthest offset cannot be negative.');
        $this->assert(!$this->export instanceof Export || ($this->export->maxRows >= 1 && $this->export->maxRows <= Export::LIMIT), \sprintf('the most rows of an export is between 1 and %d.', Export::LIMIT));

        foreach ($this->sorts as $sort) {
            $this->assert(null !== ($this->columns[$sort->field] ?? null)?->sortField, \sprintf('the column "%s" of the default sort is not sortable.', $sort->field));
        }

        foreach ($this->columns as $column) {
            $this->assert(null === $column->editing || ColumnType::Link !== $column->type && ColumnType::Block !== $column->type, \sprintf('the column "%s" cannot be edited: it is a link or a block.', $column->name));
        }

        return new TableDefinition($this->name, $this->caption, $this->domain, $this->identifier, $this->version, $this->attribute, $this->columns, $this->filters, $this->actions, $this->sorts, $this->pageSizes, $this->maxOffset, $this->export);
    }

    /**
     * @param array<string, mixed> $known
     */
    private function check(string $kind, string $name, array $known): void
    {
        $this->assert(1 === preg_match(self::NAME, $name), \sprintf('the %s name "%s" is not 1 to 64 letters, digits or underscores starting with a letter.', $kind, $name));
        $this->assert(!isset($known[$name]), \sprintf('the %s "%s" is declared twice.', $kind, $name));
    }

    private function assert(bool $condition, string $reason): void
    {
        if (!$condition) {
            throw InvalidDefinition::because($this->name, $reason);
        }
    }
}
