<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Unit\Application\Definition;

use Closure;
use IntlDateFormatter;
use Phil\DataTableBundle\Application\Definition\Action;
use Phil\DataTableBundle\Application\Definition\Choices;
use Phil\DataTableBundle\Application\Definition\Column;
use Phil\DataTableBundle\Application\Definition\ColumnType;
use Phil\DataTableBundle\Application\Definition\Editing;
use Phil\DataTableBundle\Application\Definition\Export;
use Phil\DataTableBundle\Application\Definition\Filter;
use Phil\DataTableBundle\Application\Definition\FilterType;
use Phil\DataTableBundle\Application\Definition\TableBuilder;
use Phil\DataTableBundle\Application\Definition\TableDefinition;
use Phil\DataTableBundle\Application\Exception\InvalidDefinition;
use Phil\DataTableBundle\Application\Query\SortDirection;
use Phil\DataTableBundle\Tests\Fixtures\Status;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Action::class)]
#[CoversClass(Choices::class)]
#[CoversClass(Column::class)]
#[CoversClass(Editing::class)]
#[CoversClass(Filter::class)]
#[CoversClass(TableBuilder::class)]
#[CoversClass(Export::class)]
#[CoversClass(TableDefinition::class)]
#[CoversClass(InvalidDefinition::class)]
final class DefinitionTest extends TestCase
{
    public function testAColumnSaysHowItIsRead(): void
    {
        $column = Column::text('title', 'Title');

        self::assertSame([ColumnType::Text, 'title', 'Title', 'title', null], [$column->type, $column->name, $column->label, $column->path(), $column->sortField]);
        self::assertSame('author.name', $column->of('author.name')->path());
        self::assertSame('title', $column->path(), 'a column is immutable');
        self::assertSame('author.name', $column->of('author.name')->sortable()->sortField);
        self::assertSame('b.title', $column->sortable('b.title')->sortField);
        self::assertInstanceOf(Closure::class, $column->reading(static fn (): int => 1)->reader);
    }

    public function testEachKindOfColumn(): void
    {
        self::assertSame([ColumnType::Number, 2], [Column::number('n', 'N', 2)->type, Column::number('n', 'N', 2)->decimals]);
        self::assertNull(Column::number('n', 'N')->decimals);

        $date = Column::date('d', 'D', IntlDateFormatter::SHORT, IntlDateFormatter::LONG, 'UTC');
        self::assertSame([ColumnType::Date, IntlDateFormatter::SHORT, IntlDateFormatter::LONG, 'UTC'], [$date->type, $date->dateStyle, $date->timeStyle, $date->timezone]);
        $plain = Column::date('d', 'D');
        self::assertSame([IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE, null], [$plain->dateStyle, $plain->timeStyle, $plain->timezone]);

        $enum = Column::enum('s', 'S', Status::class);
        self::assertSame([ColumnType::Enum, ['draft' => 'Draft', 'live' => 'Live'], Status::class], [$enum->type, $enum->choices, $enum->enum]);
        self::assertSame([['a' => 'A', 1 => 'B'], null], [Column::enum('s', 'S', ['a' => 'A', 1 => 'B'])->choices, Column::enum('s', 'S', ['a' => 'A'])->enum]);

        self::assertSame(ColumnType::Boolean, Column::boolean('b', 'B')->type);
        $href = static fn (): string => '/';
        self::assertSame([ColumnType::Link, $href], [Column::link('l', 'L', $href)->type, Column::link('l', 'L', $href)->href]);
        $block = Column::block('c', 'C', 't.twig', 'b');
        self::assertSame([ColumnType::Block, 't.twig', 'b'], [$block->type, $block->template, $block->block]);
        $editing = new Editing('A', static function (): void {});
        self::assertSame($editing, Column::text('t', 'T')->editable($editing)->editing);
    }

    public function testEachKindOfFilter(): void
    {
        $text = Filter::text('q', 'Q', ['a', 'b']);
        self::assertSame([FilterType::Text, 'q', 'Q', ['a', 'b']], [$text->type, $text->name, $text->label, $text->fields]);
        self::assertSame(['q'], Filter::text('q', 'Q')->fields);

        $enum = Filter::enum('s', 'S', Status::class, 'state');
        self::assertSame([FilterType::Enum, ['state'], ['draft' => 'Draft', 'live' => 'Live']], [$enum->type, $enum->fields, $enum->choices]);
        self::assertSame(['s'], Filter::enum('s', 'S', ['a' => 'A'])->fields);

        $range = Filter::dateRange('d', 'D', 'day', 'UTC');
        self::assertSame([FilterType::DateRange, ['day'], 'UTC'], [$range->type, $range->fields, $range->timezone]);
        self::assertSame([['d'], null], [Filter::dateRange('d', 'D')->fields, Filter::dateRange('d', 'D')->timezone]);

        self::assertSame([FilterType::Boolean, ['flag']], [Filter::boolean('b', 'B', 'flag')->type, Filter::boolean('b', 'B', 'flag')->fields]);
        self::assertSame(['b'], Filter::boolean('b', 'B')->fields);
    }

    public function testAnAction(): void
    {
        $handler = static function (): void {};
        $row = Action::row('a', 'A', 'ATTR', $handler);

        self::assertSame(['a', 'A', 'ATTR', $handler, true, false, true, null, false], [$row->name, $row->label, $row->attribute, $row->handler, $row->onRow, $row->onSelection, $row->confirms, $row->confirmation, $row->destructive]);
        self::assertTrue($row->accepts(1));
        self::assertFalse($row->accepts(2));

        $both = $row->alsoOnSelection();
        self::assertSame([true, true, true], [$both->onRow, $both->onSelection, $both->accepts(2)]);
        self::assertFalse($row->onSelection, 'an action is immutable');

        $bulk = Action::bulk('a', 'A', 'ATTR', $handler);
        self::assertSame([false, true], [$bulk->onRow, $bulk->onSelection]);

        self::assertSame('Sure?', $row->confirmedBy('Sure?')->confirmation);
        self::assertFalse($row->withoutConfirmation()->confirms);
        self::assertTrue($row->destructive()->destructive);
    }

    public function testATableIsBuilt(): void
    {
        $column = Column::text('title', 'Title')->sortable();
        $filter = Filter::text('q', 'Q');
        $action = Action::bulk('a', 'A', 'ATTR', static function (): void {});
        $definition = new TableBuilder('books')
            ->caption('Books')
            ->translationDomain('app')
            ->identifier('uuid')
            ->version('rev')
            ->requires('VIEW')
            ->column($column)
            ->filter($filter)
            ->action($action)
            ->sortBy('title', SortDirection::Desc)
            ->pageSizes(5, 10)
            ->maxOffset(0)
            ->build();

        self::assertSame(['books', 'Books', 'app', 'uuid', 'rev', 'VIEW'], [$definition->name, $definition->caption, $definition->domain, $definition->identifier, $definition->version, $definition->attribute]);
        self::assertSame([['title' => $column], ['q' => $filter], ['a' => $action]], [$definition->columns, $definition->filters, $definition->actions]);
        self::assertSame(['title', SortDirection::Desc], [$definition->sorts[0]->field, $definition->sorts[0]->direction]);
        self::assertSame([[5, 10], 0], [$definition->pageSizes, $definition->maxOffset]);
        self::assertNull($definition->export);

        $exported = $this->minimal()->exportable('EXPORT', 1)->build()->export;
        self::assertNotNull($exported);
        self::assertSame(['EXPORT', 1], [$exported->attribute, $exported->maxRows]);
        $defaults = $this->minimal()->exportable()->build()->export;
        self::assertNotNull($defaults);
        self::assertSame([null, 10_000], [$defaults->attribute, $defaults->maxRows]);
        self::assertSame(1_000_000, $this->minimal()->exportable(maxRows: Export::LIMIT)->build()->export?->maxRows);
    }

    public function testTheDefaultsOfATable(): void
    {
        $definition = $this->minimal()->sortBy('title')->build();

        self::assertSame(['messages', 'id', null, null, [25, 50, 100], 10_000, SortDirection::Asc], [$definition->domain, $definition->identifier, $definition->version, $definition->attribute, $definition->pageSizes, $definition->maxOffset, $definition->sorts[0]->direction]);
        self::assertSame([5], $this->minimal()->pageSizes(...['named' => 5])->build()->pageSizes);
        self::assertSame([1, 500], [$this->minimal()->pageSizes(1)->build()->pageSizes[0], $this->minimal()->pageSizes(TableBuilder::MAX_PAGE_SIZE)->build()->pageSizes[0]]);
    }

    /**
     * @param Closure(TableBuilder): TableBuilder $declare
     */
    #[DataProvider('mistakes')]
    public function testAMistakeShowsAtTheFirstBuild(Closure $declare, string $reason): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains('The table "books" is invalid: '.$reason);

        $declare($this->minimal())->build();
    }

    /**
     * @return iterable<string, array{Closure(TableBuilder): TableBuilder, string}>
     */
    public static function mistakes(): iterable
    {
        yield 'no caption' => [static fn (TableBuilder $table): TableBuilder => $table->caption(''), 'a caption is required.'];
        yield 'no page size' => [static fn (TableBuilder $table): TableBuilder => $table->pageSizes(), 'a page size is required.'];
        yield 'page size 0' => [static fn (TableBuilder $table): TableBuilder => $table->pageSizes(0), 'a page size is between 1 and 500.'];
        yield 'page size too large' => [static fn (TableBuilder $table): TableBuilder => $table->pageSizes(501), 'a page size is between 1 and 500.'];
        yield 'negative offset' => [static fn (TableBuilder $table): TableBuilder => $table->maxOffset(-1), 'the furthest offset cannot be negative.'];
        yield 'unknown sort' => [static fn (TableBuilder $table): TableBuilder => $table->sortBy('nope'), 'the column "nope" of the default sort is not sortable.'];
        yield 'unsortable sort' => [static fn (TableBuilder $table): TableBuilder => $table->column(Column::text('other', 'O'))->sortBy('other'), 'the column "other" of the default sort is not sortable.'];
        yield 'edited link' => [static fn (TableBuilder $table): TableBuilder => $table->column(Column::link('l', 'L', static fn (): string => '/')->editable(new Editing('A', static function (): void {}))), 'the column "l" cannot be edited: it is a link or a block.'];
        yield 'edited block' => [static fn (TableBuilder $table): TableBuilder => $table->column(Column::block('b', 'B', 't', 'b')->editable(new Editing('A', static function (): void {}))), 'the column "b" cannot be edited: it is a link or a block.'];
        yield 'bad column name' => [static fn (TableBuilder $table): TableBuilder => $table->column(Column::text('1a', 'A')), 'the column name "1a" is not 1 to 64 letters, digits or underscores starting with a letter.'];
        yield 'multi-line name' => [static fn (TableBuilder $table): TableBuilder => $table->filter(Filter::text("a\n", 'A')), 'the filter name "a'."\n".'" is not 1 to 64 letters, digits or underscores starting with a letter.'];
        yield 'twice' => [static fn (TableBuilder $table): TableBuilder => $table->column(Column::text('title', 'A')), 'the column "title" is declared twice.'];
        yield 'filter twice' => [static fn (TableBuilder $table): TableBuilder => $table->filter(Filter::text('a', 'A'))->filter(Filter::text('a', 'A')), 'the filter "a" is declared twice.'];
        yield 'no rows exported' => [static fn (TableBuilder $table): TableBuilder => $table->exportable(maxRows: 0), 'the most rows of an export is between 1 and 1000000.'];
        yield 'too many rows exported' => [static fn (TableBuilder $table): TableBuilder => $table->exportable(maxRows: 1_000_001), 'the most rows of an export is between 1 and 1000000.'];
        yield 'action twice' => [static fn (TableBuilder $table): TableBuilder => $table->action(Action::row('a', 'A', 'B', static function (): void {}))->action(Action::row('a', 'A', 'B', static function (): void {})), 'the action "a" is declared twice.'];
    }

    public function testNoColumnIsAMistake(): void
    {
        $this->expectExceptionMessageIsOrContains('a column is required.');

        new TableBuilder('books')->caption('C')->build();
    }

    public function testAnEditedTextColumnIsFine(): void
    {
        self::assertCount(1, $this->minimal()->build()->columns);
        self::assertCount(2, $this->minimal()->column(Column::number('n', 'N')->editable(new Editing('A', static function (): void {})))->build()->columns);
    }

    private function minimal(): TableBuilder
    {
        return new TableBuilder('books')->caption('Books')->column(Column::text('title', 'Title')->sortable());
    }
}
