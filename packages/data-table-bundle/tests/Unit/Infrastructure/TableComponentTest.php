<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Unit\Infrastructure;

use Phil\DataTableBundle\Application\Exception\AccessDenied;
use Phil\DataTableBundle\Application\Exception\NotFound;
use Phil\DataTableBundle\Application\State\TablePreference;
use Phil\DataTableBundle\Application\View\HeaderView;
use Phil\DataTableBundle\Infrastructure\Http\TableGuard;
use Phil\DataTableBundle\Infrastructure\Twig\Components\TableComponent;
use Phil\DataTableBundle\Tests\Fixtures\Kit;
use Phil\DataTableBundle\Tests\Fixtures\MemoryPreferences;
use Phil\DataTableBundle\Tests\Fixtures\Shelf;
use Phil\DataTableBundle\Tests\Fixtures\Tokens;
use Phil\DataTableBundle\Tests\Functional\App\BooksTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Metadata\UrlMapping;

#[CoversClass(TableComponent::class)]
final class TableComponentTest extends TestCase
{
    /** The fields of the filters of the books, empty. */
    private const array BLANK = ['q' => '', 'status' => '', 'published_from' => '', 'published_until' => '', 'inPrint' => ''];

    private Kit $kit;

    private Shelf $shelf;

    private MemoryPreferences $preferences;

    private RequestStack $requests;

    private Tokens $tokens;

    private TableComponent $table;

    protected function setUp(): void
    {
        $this->kit = new Kit();
        $this->shelf = new Shelf(30);
        $this->preferences = new MemoryPreferences();
        $this->requests = new RequestStack();
        $this->tokens = new Tokens();
        $this->table = $this->component();
    }

    public function testAPrefixMovesTheParametersOfTheAddress(): void
    {
        $this->table->urlPrefix = 'p_';

        $prefixed = $this->table->withPrefix(new LiveProp(url: new UrlMapping(as: 'sort')));
        self::assertInstanceOf(UrlMapping::class, $prefixed->url());
        self::assertSame('p_sort', $prefixed->url()->as);

        $bare = new LiveProp();
        self::assertSame($bare, $this->table->withPrefix($bare));
    }

    public function testTheMountRemembersThePageAndTheOtherParameters(): void
    {
        $this->requests->push(Request::create('/books?p_sort=title&p_page=2&p_size=5&p_filters[q]=x&p_columns[]=a&p_selected[]=1&p_confirm=c&p_edit=e&p_row=r&other=kept&sort=theirs'));
        $this->table->urlPrefix = 'p_';
        $this->table->restore();

        self::assertSame('/books', $this->table->path);
        self::assertSame(['other' => 'kept', 'sort' => 'theirs'], $this->table->preserved);
    }

    public function testTheMountGivesEveryFieldOfTheFiltersItsKey(): void
    {
        $this->table->filters = ['q' => 'x', 'other' => 'y'];
        $this->table->restore();

        self::assertSame(['q' => 'x', 'status' => '', 'published_from' => '', 'published_until' => '', 'inPrint' => '', 'other' => 'y'], $this->table->filters);
    }

    public function testThePageIsKeptUnderItsBaseAddress(): void
    {
        $this->requests->push(new Request(server: ['SCRIPT_FILENAME' => '/srv/app/index.php', 'SCRIPT_NAME' => '/app/index.php', 'REQUEST_URI' => '/app/books']));
        $this->table->restore();

        self::assertSame('/app/books', $this->table->path);
    }

    public function testTheMountWithoutARequest(): void
    {
        $this->table->restore();

        self::assertSame(['', []], [$this->table->path, $this->table->preserved]);
    }

    public function testThePreferenceFillsWhatTheAddressDoesNotSay(): void
    {
        $this->preferences->stored['books'] = new TablePreference(['title'], 25);
        $this->table->restore();
        self::assertSame([['title'], 25], [$this->table->columns, $this->table->size]);

        $other = $this->component();
        $other->columns = ['pages'];
        $other->size = 10;
        $other->restore();
        self::assertSame([['pages'], 10], [$other->columns, $other->size]);
    }

    public function testAnEditFromTheAddressStartsFromTheRow(): void
    {
        $this->table->edit = 'title';
        $this->table->row = '1';
        $this->table->restore();

        self::assertSame(['Book 01', '1'], [$this->table->draft, $this->table->version]);
    }

    public function testAnEditFromTheAddressNeedsItsRowAndItsColumn(): void
    {
        $this->table->edit = 'title';
        $this->table->restore();
        self::assertSame(['', 'title'], [$this->table->draft, $this->table->edit]);

        $other = $this->component();
        $other->row = '1';
        $other->restore();
        self::assertSame(['', '1'], [$other->draft, $other->row]);
    }

    public function testAForbiddenOrImpossibleEditFromTheAddressIsDropped(): void
    {
        $this->kit->access->refused = ['BOOK_EDIT'];
        $this->table->edit = 'title';
        $this->table->row = '1';
        $this->table->restore();
        self::assertSame(['', ''], [$this->table->edit, $this->table->row]);

        $this->kit->access->refused = [];
        $other = $this->component();
        $other->edit = 'nope';
        $other->row = '1';
        $other->restore();
        self::assertSame(['', ''], [$other->edit, $other->row]);
    }

    public function testTheViewReadsTheComponent(): void
    {
        $this->table->sort = '-pages';
        $this->table->size = 10;
        $this->table->page = 2;
        $this->table->filters = ['status' => 'live'];
        $this->table->columns = ['title', 'pages'];
        $this->table->selected = ['3'];
        $this->table->urlPrefix = 'p_';
        $this->table->preserved = ['other' => 'x'];

        $view = $this->table->getView();

        self::assertSame(['title', 'pages'], array_map(static fn (HeaderView $header): string => $header->name, $view->headers));
        self::assertSame('descending', $view->headers[1]->ariaSort);
        self::assertSame([2, 10, 15], [$view->pagination->page, $view->pageSize, $view->total]);
        self::assertSame('p_', $view->prefix);
        self::assertSame('?other=x&p_sort=-pages&p_filters%5Bstatus%5D=live&p_columns%5B0%5D=title&p_columns%5B1%5D=pages&p_page=2', $view->currentHref);
    }

    public function testTheViewCarriesTheQuestionAndTheEdit(): void
    {
        $this->table->confirm = 'archive';
        $this->table->row = '1';
        self::assertSame(['1'], $this->table->getView()->confirmation?->ids);

        $other = $this->component();
        $other->edit = 'title';
        $other->row = '1';
        $other->errors = ['Bad'];
        $other->draft = 'Mine';

        $edit = $other->getView()->rows[0]->cells[0]->edit;
        self::assertSame(['Mine', ['Bad']], [$edit?->value, $edit?->errors]);

        $third = $this->component();
        $third->edit = 'title';
        $third->row = '1';
        $third->draft = 'Typed';
        self::assertSame('Book 01', $third->getView()->rows[0]->cells[0]->edit?->value, 'no error: the row speaks');
    }

    public function testTheFormsAreSealed(): void
    {
        $this->table->context = ['limit' => 3];
        $guard = new TableGuard($this->tokens, 'secret');

        self::assertSame('good', $this->table->getToken());
        self::assertSame('{"limit":3}', $this->table->getEncodedContext());
        self::assertSame($guard->seal('books', '{"limit":3}'), $this->table->getSeal());
    }

    public function testTheContextReachesTheTable(): void
    {
        $this->table->context = ['limit' => 3];

        self::assertSame(3, $this->table->getView()->total);
    }

    public function testThePageToComeBackTo(): void
    {
        $this->table->path = '/books';

        self::assertSame('/books?page=2', $this->table->backTo('?page=2'));
    }

    public function testMovingAboutForgetsTheSelection(): void
    {
        $this->table->selected = ['1'];
        $this->table->goTo(3);
        self::assertSame([3, []], [$this->table->page, $this->table->selected]);

        $this->table->selected = ['1'];
        $this->table->applySort('-title');
        self::assertSame(['-title', 1, []], [$this->table->sort, $this->table->page, $this->table->selected]);

        $this->table->page = 3;
        $this->table->applyFilters();
        self::assertSame(1, $this->table->page);

        $this->table->page = 3;
        $this->table->filters = ['q' => 'x'];
        $this->table->clearFilters();
        self::assertSame([1, self::BLANK], [$this->table->page, $this->table->filters]);
    }

    public function testThePageSizeIsRemembered(): void
    {
        $this->table->page = 3;
        $this->table->size = 25;
        $this->table->columns = [1, 'title'];
        $this->table->rememberSize('good');

        self::assertSame(1, $this->table->page);
        self::assertSame([['title'], 25], [$this->preferences->stored['books']->columns, $this->preferences->stored['books']->pageSize]);
        self::assertSame(['phil_data_table'], $this->tokens->checked);

        $this->expectException(AccessDenied::class);
        $this->table->rememberSize('bad');
    }

    public function testAColumnIsShownOrHiddenAndRemembered(): void
    {
        $this->table->toggleColumn('pages', 'good');
        self::assertSame(['title', 'published', 'status', 'inPrint', 'page', 'cover'], $this->table->columns);
        self::assertSame($this->table->columns, $this->preferences->stored['books']->columns);
        self::assertTrue($this->table->columnsOpen);

        $this->table->toggleColumn('pages', 'good');
        self::assertSame(['title', 'pages', 'published', 'status', 'inPrint', 'page', 'cover'], $this->table->columns, 'back in its place');
    }

    public function testAColumnComesBackInItsPlaceAmongTheVisibleOnes(): void
    {
        $this->table->columns = ['title'];
        $this->table->toggleColumn('cover', 'good');

        self::assertSame(['title', 'cover'], $this->table->columns);
    }

    public function testTheLastColumnStays(): void
    {
        $this->table->columns = ['title'];
        $this->table->toggleColumn('title', 'good');

        self::assertSame(['title'], $this->table->columns);
    }

    public function testAnUnknownColumnChangesNothing(): void
    {
        $this->table->columns = ['title'];
        $this->table->toggleColumn('nope', 'good');

        self::assertSame(['title'], $this->table->columns);
    }

    public function testShowingAColumnNeedsTheCheck(): void
    {
        $this->expectException(AccessDenied::class);

        $this->table->toggleColumn('pages', 'bad');
    }

    public function testThePageIsSelectedOrNot(): void
    {
        $this->table->size = 10;
        $this->table->toggleAll();
        self::assertSame(array_map(strval(...), [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]), $this->table->selected);

        $this->table->toggleAll();
        self::assertSame([], $this->table->selected);
    }

    public function testAnActionAsksFirst(): void
    {
        $this->table->ask('archive', 'bad', '2');

        self::assertSame(['archive', '2', []], [$this->table->confirm, $this->table->row, $this->shelf->archived]);
        self::assertSame([], $this->tokens->checked, 'asking changes nothing');
    }

    public function testAnActionAsksAboutTheSelection(): void
    {
        $this->table->selected = ['2', 3, '4'];
        $this->table->ask('archive', 'good');

        self::assertSame(['archive', ''], [$this->table->confirm, $this->table->row]);

        $this->table->perform('good');
        self::assertSame([2, 4], $this->shelf->archived);
        self::assertSame([], $this->table->selected);
    }

    public function testAnActionThatAsksNothingRunsAfterItsChecks(): void
    {
        $this->table->ask('touch', 'good', '2');

        self::assertSame([[2], '', ''], [$this->shelf->archived, $this->table->confirm, $this->table->row]);
        self::assertSame('table.action.done', $this->table->notice);
    }

    public function testAnActionThatAsksNothingNeedsItsToken(): void
    {
        $this->expectException(AccessDenied::class);

        $this->table->ask('touch', 'bad', '2');
    }

    public function testAskingIsCheckedToo(): void
    {
        $this->expectException(NotFound::class);

        $this->table->ask('nope', 'good', '2');
    }

    public function testARunRefusedSaysSo(): void
    {
        $this->table->selected = ['1'];
        $this->table->confirm = 'refuse';
        $this->table->row = '2';
        $this->table->perform('good');

        self::assertSame(['Not this one.', '', '', ['1']], [$this->table->notice, $this->table->confirm, $this->table->row, $this->table->selected]);
    }

    public function testARunNeedsItsToken(): void
    {
        $this->table->confirm = 'archive';
        $this->table->row = '1';

        try {
            $this->table->perform('bad');
            self::fail('The action ran.');
        } catch (AccessDenied) {
            self::assertSame([], $this->shelf->archived);
        }
    }

    public function testDismissing(): void
    {
        $this->table->confirm = 'archive';
        $this->table->row = '1';
        $this->table->dismiss();

        self::assertSame(['', ''], [$this->table->confirm, $this->table->row]);
    }

    public function testAnEditStarts(): void
    {
        $this->table->confirm = 'archive';
        $this->table->startEdit('2', 'title');

        self::assertSame(['title', '2', '', 'Book 02', '1'], [$this->table->edit, $this->table->row, $this->table->confirm, $this->table->draft, $this->table->version]);
    }

    public function testAnEditIsSaved(): void
    {
        $this->table->startEdit('2', 'title');
        $this->table->draft = 'New';
        $this->table->save('good');

        self::assertSame('New', $this->shelf->books[1]->title);
        self::assertSame(['', '', '', null, 'table.edit.saved', []], [$this->table->edit, $this->table->row, $this->table->draft, $this->table->version, $this->table->notice, $this->table->errors]);
    }

    public function testASaveNeedsItsToken(): void
    {
        $this->table->startEdit('2', 'title');
        $this->table->draft = 'New';

        try {
            $this->table->save('bad');
            self::fail('The edit ran.');
        } catch (AccessDenied) {
            self::assertSame('Book 02', $this->shelf->books[1]->title);
        }
    }

    public function testAnInvalidEditStays(): void
    {
        $this->table->startEdit('2', 'title');
        $this->table->draft = '';
        $this->table->save('good');

        self::assertSame(['title', '2', ''], [$this->table->edit, $this->table->row, $this->table->draft]);
        self::assertCount(1, $this->table->errors);
    }

    public function testAConflictShowsTheCurrentValue(): void
    {
        $this->table->startEdit('2', 'title');
        $this->shelf->books[1]->version = 5;
        $this->shelf->books[1]->title = 'Theirs';
        $this->table->draft = 'Mine';
        $this->table->save('good');

        self::assertSame(['Theirs', '5', ['table.edit.conflict']], [$this->table->draft, $this->table->version, $this->table->errors]);
    }

    private function component(): TableComponent
    {
        $table = new TableComponent(
            $this->kit->tables(new BooksTable($this->shelf)),
            $this->kit->views,
            $this->kit->operations,
            new TableGuard($this->tokens, 'secret'),
            $this->preferences,
            $this->requests,
            $this->kit->translator,
        );
        $table->name = 'books';

        return $table;
    }
}
