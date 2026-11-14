<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Unit\Application\View;

use DateTimeImmutable;
use IntlDateFormatter;
use Phil\DataTableBundle\Application\Contract\DataSource;
use Phil\DataTableBundle\Application\Definition\Action;
use Phil\DataTableBundle\Application\Definition\Column;
use Phil\DataTableBundle\Application\Definition\Editing;
use Phil\DataTableBundle\Application\Definition\Filter;
use Phil\DataTableBundle\Application\Definition\TableBuilder;
use Phil\DataTableBundle\Application\OpenTable;
use Phil\DataTableBundle\Application\Query\ResultPage;
use Phil\DataTableBundle\Application\Query\SortDirection;
use Phil\DataTableBundle\Application\Query\TableQuery;
use Phil\DataTableBundle\Application\Source\InMemoryDataSource;
use Phil\DataTableBundle\Application\State\TableState;
use Phil\DataTableBundle\Application\View\ActionView;
use Phil\DataTableBundle\Application\View\CellView;
use Phil\DataTableBundle\Application\View\ChoiceView;
use Phil\DataTableBundle\Application\View\ColumnOptionView;
use Phil\DataTableBundle\Application\View\ConfirmationView;
use Phil\DataTableBundle\Application\View\EditView;
use Phil\DataTableBundle\Application\View\FilterView;
use Phil\DataTableBundle\Application\View\Formats;
use Phil\DataTableBundle\Application\View\HeaderView;
use Phil\DataTableBundle\Application\View\PageLink;
use Phil\DataTableBundle\Application\View\PaginationView;
use Phil\DataTableBundle\Application\View\RowRenderer;
use Phil\DataTableBundle\Application\View\RowView;
use Phil\DataTableBundle\Application\View\TableView;
use Phil\DataTableBundle\Application\View\TableViews;
use Phil\DataTableBundle\Tests\Fixtures\Kit;
use Phil\DataTableBundle\Tests\Fixtures\Level;
use Phil\DataTableBundle\Tests\Fixtures\Status;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

#[CoversClass(TableViews::class)]
#[CoversClass(RowRenderer::class)]
#[CoversClass(Formats::class)]
#[CoversClass(TableView::class)]
#[CoversClass(HeaderView::class)]
#[CoversClass(RowView::class)]
#[CoversClass(CellView::class)]
#[CoversClass(EditView::class)]
#[CoversClass(ChoiceView::class)]
#[CoversClass(ActionView::class)]
#[CoversClass(FilterView::class)]
#[CoversClass(ColumnOptionView::class)]
#[CoversClass(PageLink::class)]
#[CoversClass(PaginationView::class)]
#[CoversClass(ConfirmationView::class)]
final class TableViewsTest extends TestCase
{
    private Kit $kit;

    protected function setUp(): void
    {
        $this->kit = new Kit();
    }

    public function testTheTableItself(): void
    {
        $view = $this->view(new TableState(size: 10, urlPrefix: 'p_', preserved: ['other' => 'x']));

        self::assertSame(['t', 'p_', 'phil-data-table-p_t', 'Caption', [10, 20], 10, 30], [$view->name, $view->prefix, $view->formId, $view->caption, $view->pageSizes, $view->pageSize, $view->total]);
        self::assertSame([['name' => 'other', 'value' => 'x']], $view->hiddenFields);
        self::assertSame('?other=x&p_page=1', $view->currentHref);
        self::assertSame('?other=x', $view->clearHref);
        self::assertFalse($view->filtered);
    }

    public function testTheHeadings(): void
    {
        $view = $this->view(new TableState(sort: '-pages', size: 10));
        [$title, $pages, $when] = $view->headers;

        self::assertSame(['title', 'Title', null, 'title'], [$title->name, $title->label, $title->ariaSort, $title->nextSort]);
        self::assertSame(['descending', 'pages'], [$pages->ariaSort, $pages->nextSort]);
        self::assertSame([null, null], [$when->ariaSort, $when->nextSort]);

        $ascending = $this->view(new TableState(sort: 'pages'));
        self::assertSame(['ascending', '-pages'], [$ascending->headers[1]->ariaSort, $ascending->headers[1]->nextSort]);
    }

    public function testACellOfEachKind(): void
    {
        $row = $this->view(new TableState(size: 10))->rows[0];
        [$title, $pages, $when, $status, $print, $site, $cover] = $row->cells;

        self::assertSame(['1', 'Row 01'], [$row->id, $row->label]);
        self::assertSame(['title', 'Title', 'text', true, 'Row 01', 'Row 01'], [$title->name, $title->label, $title->type, $title->header, $title->text, $title->value]);
        self::assertFalse($pages->header);
        self::assertSame('1,001', $pages->text);
        self::assertSame('Jan 2, 2026', $when->text);
        self::assertSame('Live', $status->text);
        self::assertSame('table.yes', $print->text);
        self::assertSame('table.no', $this->view(new TableState(size: 10))->rows[2]->cells[4]->text);
        self::assertSame(['https://example.test/1', 'https://example.test/1'], [$site->href, $site->text]);
        self::assertNull($title->href);
        self::assertSame(['t.twig', 'cover', 'cover', 'block'], [$cover->template, $cover->block, $cover->name, $cover->type]);
        self::assertSame($cover->row, $title->row);
        self::assertIsObject($cover->row);
    }

    public function testValuesThatAreNotOfTheirColumnStayText(): void
    {
        $table = new TableBuilder('t')->caption('C')
            ->column(Column::number('a', 'A'))->column(Column::date('b', 'B'))->column(Column::enum('c', 'C', ['x' => 'X']))->column(Column::number('d', 'D', 2))
            ->build();
        $view = $this->kit->views->view(new OpenTable($table, new InMemoryDataSource([['id' => 1, 'a' => 'n/a', 'b' => 'soon', 'c' => 'y', 'd' => null], ['id' => 2, 'a' => '12.5', 'b' => null, 'c' => null, 'd' => 3]])), new TableState());

        self::assertSame(['n/a', 'soon', 'y', ''], array_map(static fn (CellView $cell): string => $cell->text, $view->rows[0]->cells));
        self::assertSame(['12.5', '', '', '3.00'], array_map(static fn (CellView $cell): string => $cell->text, $view->rows[1]->cells));
    }

    #[DataProvider('hrefs')]
    public function testALinkKeepsToSafeAddresses(string $href, bool $kept): void
    {
        $table = new TableBuilder('t')->caption('C')->column(Column::link('a', 'A', static fn (): string => $href))->build();
        $cell = $this->kit->views->view(new OpenTable($table, new InMemoryDataSource([['id' => 1, 'a' => 'x']])), new TableState())->rows[0]->cells[0];

        self::assertSame($kept ? $href : null, $cell->href);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function hrefs(): iterable
    {
        yield 'https' => ['https://a.test/', true];
        yield 'http' => ['HTTP://a.test/', true];
        yield 'mailto' => ['mailto:a@a.test', true];
        yield 'tel' => ['tel:+15551234567', true];
        yield 'root' => ['/a/b', true];
        yield 'query' => ['?a=b', true];
        yield 'fragment' => ['#a', true];
        yield 'relative' => ['a/b', true];
        yield 'word' => ['a', true];
        yield 'relative query' => ['a?b:c', true];
        yield 'javascript' => ['javascript:alert(1)', false];
        yield 'tabbed' => ["java\tscript:alert(1)", false];
        yield 'data' => ['data:text/html,x', false];
        yield 'other host' => ['//a.test', false];
        yield 'backslashes' => ['/\\a.test', false];
        yield 'leading backslash' => ['\\\\a.test/x', false];
        yield 'empty' => ['', false];
    }

    public function testALinkWithoutAnAddressIsText(): void
    {
        $table = new TableBuilder('t')->caption('C')->column(Column::link('a', 'A', static fn (): ?string => null))->build();

        self::assertNull($this->kit->views->view(new OpenTable($table, new InMemoryDataSource([['id' => 1, 'a' => 'x']])), new TableState())->rows[0]->cells[0]->href);
    }

    public function testChoicesAreTranslatedOnceAndFallBackOnTheirValue(): void
    {
        $table = new TableBuilder('t')->caption('C')->column(Column::enum('a', 'A', ['x' => 'X']))->build();
        $view = $this->kit->views->view(new OpenTable($table, new InMemoryDataSource([['id' => 1, 'a' => 'x'], ['id' => 2, 'a' => 'other']])), new TableState());

        self::assertSame(['X', 'other'], [$view->rows[0]->cells[0]->text, $view->rows[1]->cells[0]->text]);
    }

    public function testTheSortIsCarried(): void
    {
        $view = $this->view(new TableState(sort: 'pages'));

        self::assertSame([['name' => 'sort', 'value' => 'pages']], $view->hiddenFields);
        self::assertStringContainsString('sort=pages', $view->currentHref);
    }

    public function testAnEmptyBooleanIsEmpty(): void
    {
        $table = new TableBuilder('t')->caption('C')->column(Column::boolean('a', 'A'))->build();

        self::assertSame('', $this->kit->views->view(new OpenTable($table, new InMemoryDataSource([['id' => 1, 'a' => null]])), new TableState())->rows[0]->cells[0]->text);
    }

    public function testChoicesOfAnEnumOfIntegers(): void
    {
        $table = new TableBuilder('t')->caption('C')->column(Column::enum('a', 'A', Level::class)->editable(new Editing('E', static function (): void {})))->filter(Filter::enum('a', 'A', Level::class))->build();
        $view = $this->kit->views->view(new OpenTable($table, new InMemoryDataSource([['id' => 1, 'a' => Level::High]])), new TableState(filters: ['a' => '2'], edit: 'a', row: '1'));

        self::assertSame('High', $view->rows[0]->cells[0]->text);
        self::assertSame(['', '1', '2'], array_map(static fn (ChoiceView $choice): string => $choice->value, $view->rows[0]->cells[0]->edit->choices ?? []));
        self::assertSame(['1', '2'], array_map(static fn (ChoiceView $choice): string => $choice->value, $view->filters[0]->choices));
    }

    public function testTheVisibleColumns(): void
    {
        $view = $this->view(new TableState(columns: ['pages', 'when']));

        self::assertSame(['pages', 'when'], array_map(static fn (HeaderView $header): string => $header->name, $view->headers));
        self::assertTrue($view->rows[0]->cells[0]->header, 'the first visible cell heads the row');
        self::assertSame([['title', 'Title', false], ['pages', 'Pages', true]], array_map(static fn (ColumnOptionView $option): array => [$option->name, $option->label, $option->visible], \array_slice($view->columnOptions, 0, 2)));
        self::assertTrue($this->view(new TableState())->columnOptions[0]->visible);
        self::assertStringContainsString('columns%5B0%5D=pages', $view->clearHref);
    }

    public function testTheFilters(): void
    {
        $view = $this->view(new TableState(filters: ['q' => 'Row', 'status' => 'draft', 'inPrint' => '1', 'when_from' => '2026-01-02', 'when_until' => '2026-01-20']));
        [$q, $status, $print, $when] = $view->filters;

        self::assertTrue($view->filtered);
        self::assertSame(['q', 'text', 'Search', 'Row', '', '', []], [$q->name, $q->type, $q->label, $q->value, $q->from, $q->until, $q->choices]);
        self::assertSame([['draft', 'Draft'], ['live', 'Live']], array_map(static fn (ChoiceView $choice): array => [$choice->value, $choice->label], $status->choices));
        self::assertSame('draft', $status->value);
        self::assertSame([['1', 'table.yes'], ['0', 'table.no']], array_map(static fn (ChoiceView $choice): array => [$choice->value, $choice->label], $print->choices));
        self::assertSame(['date_range', '2026-01-02', '2026-01-20', ''], [$when->type, $when->from, $when->until, $when->value]);
        self::assertSame('?', $view->clearHref);
        self::assertStringContainsString('filters%5Bq%5D=Row', $view->currentHref);
    }

    public function testThePagination(): void
    {
        $pagination = $this->view(new TableState(page: 2, size: 10))->pagination;

        self::assertSame([2, 3, 11, 20, false], [$pagination->page, $pagination->lastPage, $pagination->first, $pagination->last, $pagination->truncated]);
        self::assertSame(['?page=1', '?page=3'], [$pagination->previousHref, $pagination->nextHref]);
        self::assertSame([[1, false], [2, true], [3, false]], array_map(static fn (?PageLink $link): array => [$link?->number, $link?->current], $pagination->links));
        self::assertSame('?page=3', $pagination->links[2]?->href);

        $first = $this->view(new TableState())->pagination;
        self::assertNull($first->previousHref);
        self::assertSame('?page=2', $first->nextHref);

        self::assertNull($this->view(new TableState(page: 3))->pagination->nextHref);
    }

    public function testPageNumbersShowAWindowAndTheEnds(): void
    {
        $rows = array_map(static fn (int $id): array => ['id' => $id, 'title' => 'x'], range(1, 100));
        $table = new TableBuilder('t')->caption('C')->column(Column::text('title', 'T'))->pageSizes(5)->build();
        $open = new OpenTable($table, new InMemoryDataSource($rows));
        $numbers = fn (int $page): array => array_map(static fn (?PageLink $link): ?int => $link?->number, $this->kit->views->view($open, new TableState(page: $page))->pagination->links);

        self::assertSame([1, null, 8, 9, 10, 11, 12, null, 20], $numbers(10));
        self::assertSame([1, 2, 3, null, 20], $numbers(1));
        self::assertSame([1, 2, 3, 4, 5, null, 20], $numbers(3));
        self::assertSame([1, null, 3, 4, 5, 6, 7, null, 20], $numbers(5));
        self::assertSame([1, null, 18, 19, 20], $numbers(20));
    }

    public function testALastPageThatIsNotFull(): void
    {
        $rows = array_map(static fn (int $id): array => ['id' => $id], range(1, 31));
        $table = new TableBuilder('t')->caption('C')->column(Column::text('a', 'A'))->pageSizes(10)->build();

        self::assertSame(4, $this->kit->views->view(new OpenTable($table, new InMemoryDataSource($rows)), new TableState())->pagination->lastPage);
    }

    public function testAPageBeyondTheLastShowsTheLast(): void
    {
        $view = $this->view(new TableState(page: 9, size: 10));

        self::assertSame([3, 3, 21, 30], [$view->pagination->page, $view->pagination->lastPage, $view->pagination->first, $view->pagination->last]);
        self::assertCount(10, $view->rows);
        self::assertSame('?page=3', $view->currentHref);
    }

    public function testAPageIsExactlyTheLastOneAsked(): void
    {
        $source = new class(new InMemoryDataSource([['id' => 1]])) implements DataSource {
            public int $fetched = 0;

            public function __construct(private readonly InMemoryDataSource $inner)
            {
            }

            public function fetch(TableQuery $query): ResultPage
            {
                ++$this->fetched;

                return $this->inner->fetch($query);
            }

            public function find(array $ids): array
            {
                return $this->inner->find($ids);
            }
        };
        $table = new TableBuilder('t')->caption('C')->column(Column::text('a', 'A'))->build();
        $this->kit->views->view(new OpenTable($table, $source), new TableState());

        self::assertSame(1, $source->fetched);
    }

    public function testNothingToShow(): void
    {
        $table = new TableBuilder('t')->caption('C')->column(Column::text('a', 'A'))->build();
        $view = $this->kit->views->view(new OpenTable($table, new InMemoryDataSource([])), new TableState());

        self::assertSame([0, 0, 1, 1], [$view->pagination->first, $view->pagination->last, $view->pagination->page, $view->pagination->lastPage]);
        self::assertSame(1, $view->width());
    }

    public function testMoreRowsThanThePagesReach(): void
    {
        $rows = array_map(static fn (int $id): array => ['id' => $id], range(1, 30));
        $table = new TableBuilder('t')->caption('C')->column(Column::text('a', 'A'))->pageSizes(10)->maxOffset(10)->build();
        $pagination = $this->kit->views->view(new OpenTable($table, new InMemoryDataSource($rows)), new TableState(page: 9))->pagination;

        self::assertSame([2, true], [$pagination->lastPage, $pagination->truncated]);

        $all = new TableBuilder('t')->caption('C')->column(Column::text('a', 'A'))->pageSizes(15)->build();
        self::assertFalse($this->kit->views->view(new OpenTable($all, new InMemoryDataSource($rows)), new TableState())->pagination->truncated);
    }

    public function testRowActionsAskTheVoterOnEachRow(): void
    {
        $this->kit->access->refused = ['B_ATTR'];
        $view = $this->view(new TableState(size: 10, selected: ['2']));
        $row = $view->rows[0];

        self::assertSame(['archive'], array_map(static fn (ActionView $action): string => $action->name, $row->actions));
        self::assertSame(['Archive', true, '?confirm=archive&row=1'], [$row->actions[0]->label, $row->actions[0]->destructive, $row->actions[0]->href]);
        self::assertTrue($view->hasRowActions);
        self::assertFalse($view->rows[0]->selected);
        self::assertTrue($view->rows[1]->selected);
        self::assertSame(['archive'], array_map(static fn (ActionView $action): string => $action->name, $view->bulkActions));
        self::assertSame(9, $view->width(), 'seven columns, a selection, the actions');
    }

    public function testEveryActionOfARowAndOfASelection(): void
    {
        $handler = static function (): void {};
        $table = new TableBuilder('t')->caption('C')->column(Column::text('a', 'A'))
            ->action(Action::row('one', 'One', 'X', $handler)->alsoOnSelection())
            ->action(Action::row('two', 'Two', 'X', $handler)->alsoOnSelection())
            ->build();
        $view = $this->kit->views->view(new OpenTable($table, new InMemoryDataSource([['id' => 1, 'a' => 'x']])), new TableState());
        $names = static fn (ActionView $action): string => $action->name;

        self::assertSame(['one', 'two'], array_map($names, $view->rows[0]->actions));
        self::assertSame(['one', 'two'], array_map($names, $view->bulkActions));
    }

    public function testNoActionLeavesNoColumn(): void
    {
        $this->kit->access->refused = ['A_ATTR', 'B_ATTR'];
        $view = $this->view(new TableState());

        self::assertSame([[], false, 7], [$view->bulkActions, $view->hasRowActions, $view->width()]);
    }

    public function testABulkActionNeedsOneRowThatAllowsIt(): void
    {
        $table = new TableBuilder('t')->caption('C')->column(Column::text('a', 'A'))->action(Action::bulk('b', 'B', 'ATTR', static function (): void {}))->build();
        $empty = $this->kit->views->view(new OpenTable($table, new InMemoryDataSource([])), new TableState());

        self::assertSame([], $empty->bulkActions);
    }

    public function testTheQuestionOfAnAction(): void
    {
        $view = $this->view(new TableState(size: 10, confirm: 'archive', row: '4'));
        $question = $view->confirmation;

        self::assertNotNull($question);
        self::assertSame(['archive', 'Archive', ['4'], true], [$question->action, $question->label, $question->ids, $question->destructive]);
        self::assertSame('Sure of 1?', $question->question);
        self::assertSame('?', $question->cancelHref);
    }

    public function testTheQuestionOfASelection(): void
    {
        $question = $this->view(new TableState(selected: ['1', '2'], confirm: 'plain'))->confirmation;

        self::assertNotNull($question);
        self::assertSame(['1', '2'], $question->ids);
        self::assertSame('Plain', $question->label);
        self::assertSame('table.confirm.question', $question->question);

        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['table.confirm.question' => '%action% on %count%'], 'en', 'phil_data_table');

        $kit = new Kit($translator);
        $table = new TableBuilder('t')->caption('C')->column(Column::text('a', 'A'))->action(Action::bulk('b', 'B', 'X', static function (): void {}))->build();
        $worded = $kit->views->view(new OpenTable($table, new InMemoryDataSource([['id' => 1], ['id' => 2]])), new TableState(selected: ['1', '2'], confirm: 'b'))->confirmation;
        self::assertSame('B on 2', $worded?->question);
    }

    public function testNoQuestionWithoutRightsOrRows(): void
    {
        self::assertNull($this->view(new TableState(confirm: 'nope', row: '1'))->confirmation);
        self::assertNull($this->view(new TableState(confirm: 'archive', row: '99'))->confirmation);
        self::assertNull($this->view(new TableState(confirm: 'plain'))->confirmation);
        $this->kit->access->refused = ['A_ATTR'];
        self::assertNull($this->view(new TableState(confirm: 'archive', row: '1'))->confirmation);
    }

    public function testACellBeingEdited(): void
    {
        $view = $this->view(new TableState(size: 10, edit: 'title', row: '1'));
        $edit = $view->rows[0]->cells[0]->edit;

        self::assertNotNull($edit);
        self::assertSame(['text', 'Row 01', [], '3', [], '?'], [$edit->input, $edit->value, $edit->choices, $edit->version, $edit->errors, $edit->cancelHref]);
        self::assertNull($view->rows[1]->cells[0]->edit, 'another row');
        self::assertNull($view->rows[0]->cells[1]->edit, 'another column');
        self::assertSame('?edit=pages&row=1', $view->rows[0]->cells[1]->editHref);
        self::assertSame('?edit=title&row=2', $view->rows[1]->cells[0]->editHref);
    }

    public function testTheRowBeingEditedIsSaidToHaveLeftThePage(): void
    {
        self::assertFalse($this->view(new TableState(size: 10, edit: 'title', row: '1'))->editLeft);
        self::assertTrue($this->view(new TableState(page: 2, size: 10, edit: 'title', row: '1'))->editLeft);
        self::assertFalse($this->view(new TableState(page: 2, size: 10, row: '1'))->editLeft, 'a row without an edit');
        self::assertFalse($this->view(new TableState(page: 2, size: 10, edit: 'title'))->editLeft, 'an edit without a row');
        self::assertFalse($this->view(new TableState(page: 2, size: 10))->editLeft);
    }

    public function testEditErrorsAndTheDraft(): void
    {
        $edit = $this->view(new TableState(edit: 'title', row: '1', errors: ['Bad'], draft: 'Mine'))->rows[0]->cells[0]->edit;

        self::assertSame(['Mine', ['Bad']], [$edit?->value, $edit?->errors]);
    }

    public function testTheControlOfEachColumn(): void
    {
        $pages = $this->view(new TableState(edit: 'pages', row: '1'))->rows[0]->cells[1]->edit;
        self::assertSame(['number', '1001'], [$pages?->input, $pages?->value]);

        $when = $this->view(new TableState(edit: 'when', row: '1'))->rows[0]->cells[2]->edit;
        self::assertSame(['date', '2026-01-02'], [$when?->input, $when?->value]);

        $status = $this->view(new TableState(edit: 'status', row: '1'))->rows[0]->cells[3]->edit;
        self::assertNotNull($status);
        self::assertSame(['select', 'live'], [$status->input, $status->value]);
        self::assertSame([['', ''], ['draft', 'Draft'], ['live', 'Live']], array_map(static fn (ChoiceView $choice): array => [$choice->value, $choice->label], $status->choices));

        $print = $this->view(new TableState(edit: 'print', row: '1'))->rows[0]->cells[4]->edit;
        self::assertNotNull($print);
        self::assertSame(['select', '1'], [$print->input, $print->value]);
        self::assertSame([['1', 'table.yes'], ['0', 'table.no']], array_map(static fn (ChoiceView $choice): array => [$choice->value, $choice->label], $print->choices));
    }

    public function testACellTheVoterRefusesIsNotEditable(): void
    {
        $this->kit->access->refused = ['E_ATTR'];
        $cell = $this->view(new TableState(edit: 'title', row: '1'))->rows[0]->cells[0];

        self::assertSame([null, null], [$cell->edit, $cell->editHref]);
    }

    public function testATableWithoutVersionEditsWithout(): void
    {
        $table = new TableBuilder('t')->caption('C')->column(Column::text('a', 'A')->editable(new Editing('E', static function (): void {})))->build();
        $edit = $this->kit->views->view(new OpenTable($table, new InMemoryDataSource([['id' => 1, 'a' => 'x']])), new TableState(edit: 'a', row: '1'))->rows[0]->cells[0]->edit;

        self::assertNull($edit?->version);
    }

    public function testFormatsFollowTheLocale(): void
    {
        $formats = new Formats('fr');
        $number = Column::number('n', 'N', 1);

        self::assertSame("1\u{202F}234,5", $formats->number($number, 1234.5));
        self::assertSame('12,0', $formats->number($number, '12'));
        self::assertSame('1,3', $formats->number($number, 1.26));
        self::assertSame('2 janv. 2026', $formats->date(Column::date('d', 'D', timezone: 'UTC'), new DateTimeImmutable('2026-01-02 12:00 UTC')));
        self::assertSame('01/01/2026 19:00', $formats->date(Column::date('t', 'T', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT, 'America/Toronto'), new DateTimeImmutable('2026-01-02 00:00 UTC')));
        self::assertSame('1234.5', str_replace(',', '', new Formats('en')->number(Column::number('m', 'M'), 1234.5)));
    }

    /**
     * @param array<string, mixed>|object $row
     */
    private function site(array|object $row): string
    {
        self::assertInstanceOf(stdClass::class, $row);
        self::assertIsInt($row->id);

        return 'https://example.test/'.$row->id;
    }

    private function view(TableState $state): TableView
    {
        $edit = new Editing('E_ATTR', static function (): void {});
        $table = new TableBuilder('t')
            ->caption('Caption')
            ->version('version')
            ->column(Column::text('title', 'Title')->sortable()->editable($edit))
            ->column(Column::number('pages', 'Pages', 0)->sortable()->editable($edit))
            ->column(Column::date('when', 'When', timezone: 'UTC')->editable($edit))
            ->column(Column::enum('status', 'Status', Status::class)->editable($edit))
            ->column(Column::boolean('print', 'Print')->editable($edit))
            ->column(Column::link('site', 'Site', $this->site(...))->reading($this->site(...)))
            ->column(Column::block('cover', 'Cover', 't.twig', 'cover'))
            ->filter(Filter::text('q', 'Search'))
            ->filter(Filter::enum('status', 'Status', Status::class))
            ->filter(Filter::boolean('inPrint', 'Print', 'print'))
            ->filter(Filter::dateRange('when', 'When', timezone: 'UTC'))
            ->action(Action::row('archive', 'Archive', 'A_ATTR', static function (): void {})->alsoOnSelection()->destructive()->confirmedBy('Sure of %count%?'))
            ->action(Action::bulk('plain', 'Plain', 'B_ATTR', static function (): void {}))
            ->sortBy('title', SortDirection::Asc)
            ->pageSizes(10, 20)
            ->build();
        $rows = [];

        for ($id = 1; $id <= 30; ++$id) {
            $row = new stdClass();
            $row->id = $id;
            $row->title = \sprintf('Row %02d', $id);
            $row->pages = 1000 + $id;
            $row->when = new DateTimeImmutable(\sprintf('2026-01-%02d 12:00 UTC', $id % 28 + 1));
            $row->status = 0 === $id % 2 ? Status::Draft : Status::Live;
            $row->print = 0 !== $id % 3;
            $row->inPrint = $row->print;
            $row->q = $row->title;
            $row->version = 3;
            $rows[] = $row;
        }

        return $this->kit->views->view(new OpenTable($table, new InMemoryDataSource($rows)), $state);
    }
}
