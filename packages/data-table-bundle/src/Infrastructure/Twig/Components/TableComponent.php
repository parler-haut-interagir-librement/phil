<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Infrastructure\Twig\Components;

use Phil\DataTableBundle\Application\Contract\TablePreferences;
use Phil\DataTableBundle\Application\Definition\FilterType;
use Phil\DataTableBundle\Application\EditStatus;
use Phil\DataTableBundle\Application\Exception\AccessDenied;
use Phil\DataTableBundle\Application\Exception\ActionRefused;
use Phil\DataTableBundle\Application\Exception\NotFound;
use Phil\DataTableBundle\Application\OpenTable;
use Phil\DataTableBundle\Application\State\TablePreference;
use Phil\DataTableBundle\Application\State\TableState;
use Phil\DataTableBundle\Application\TableOperations;
use Phil\DataTableBundle\Application\Tables;
use Phil\DataTableBundle\Application\View\RowView;
use Phil\DataTableBundle\Application\View\TableView;
use Phil\DataTableBundle\Application\View\TableViews;
use Phil\DataTableBundle\Infrastructure\Export\TableExports;
use Phil\DataTableBundle\Infrastructure\Http\TableGuard;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\Metadata\UrlMapping;
use Symfony\UX\TwigComponent\Attribute\PostMount;

/**
 * A table on a page, `<twig:PhilDataTable:Table name="books" />`. Its sort, page, size, filters and columns are in the
 * address, so a link, a reload or a browser without JavaScript shows the same table; every value of the address is
 * checked against the table's declaration before it reaches a query, and every change asks its voter and its CSRF
 * check again.
 */
#[AsLiveComponent(name: 'PhilDataTable:Table', template: '@PhilDataTable/components/Table.html.twig', exposePublicProps: false)]
final class TableComponent
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $name = '';

    /** @var array<string, scalar|null> what the page gives the table's data source; signed, a browser cannot change it */
    #[LiveProp]
    public array $context = [];

    /** Put before the parameters of the address, when a page holds several tables. */
    #[LiveProp]
    public string $urlPrefix = '';

    /** The path of the page, for a plain form to come back to. */
    #[LiveProp]
    public string $path = '';

    /** @var array<array-key, mixed> the other parameters of the page's address, kept in the table's links */
    #[LiveProp]
    public array $preserved = [];

    #[LiveProp(writable: true, url: new UrlMapping(as: 'sort'), modifier: 'withPrefix')]
    public string $sort = '';

    #[LiveProp(writable: true, url: new UrlMapping(as: 'page'), modifier: 'withPrefix')]
    public int $page = 1;

    #[LiveProp(writable: true, url: new UrlMapping(as: 'size'), modifier: 'withPrefix')]
    public int $size = 0;

    /** @var array<array-key, mixed> */
    #[LiveProp(writable: true, url: new UrlMapping(as: 'filters'), modifier: 'withPrefix')]
    public array $filters = [];

    /** @var array<array-key, mixed> the visible columns, all of them when empty */
    #[LiveProp(writable: true, url: new UrlMapping(as: 'columns'), modifier: 'withPrefix')]
    public array $columns = [];

    /** @var array<array-key, mixed> */
    #[LiveProp(writable: true, url: new UrlMapping(as: 'selected'), modifier: 'withPrefix')]
    public array $selected = [];

    /** The action whose question is shown. */
    #[LiveProp(writable: true, url: new UrlMapping(as: 'confirm'), modifier: 'withPrefix')]
    public string $confirm = '';

    /** The column being edited. */
    #[LiveProp(writable: true, url: new UrlMapping(as: 'edit'), modifier: 'withPrefix')]
    public string $edit = '';

    /** The row of the question or of the edit. */
    #[LiveProp(writable: true, url: new UrlMapping(as: 'row'), modifier: 'withPrefix')]
    public string $row = '';

    /** What is typed in the cell being edited. */
    #[LiveProp(writable: true)]
    public string $draft = '';

    /** The row's version when its edit started. */
    #[LiveProp]
    public ?string $version = null;

    /** What the live region says after a change, for this render. */
    public string $notice = '';

    /** @var list<string> what stopped the edit, for this render */
    public array $errors = [];

    /** Whether the list of columns stays open in this render. */
    public bool $columnsOpen = false;

    public function __construct(
        private readonly Tables $tables,
        private readonly TableViews $views,
        private readonly TableOperations $operations,
        private readonly TableGuard $guard,
        private readonly TablePreferences $preferences,
        private readonly RequestStack $requests,
        private readonly TranslatorInterface $translator,
        private readonly ?TableExports $exports = null,
    ) {
    }

    public function withPrefix(LiveProp $prop): LiveProp
    {
        $mapping = $prop->url();

        return $mapping instanceof UrlMapping ? $prop->withUrl(new UrlMapping(as: $this->urlPrefix.$mapping->as)) : $prop;
    }

    /**
     * Fills in what the page's address does not say, from the viewer's preference, and remembers the page.
     */
    #[PostMount]
    public function restore(): void
    {
        $request = $this->requests->getCurrentRequest();

        if ($request instanceof Request) {
            $this->path = $request->getBaseUrl().$request->getPathInfo();
            $own = array_flip(array_map(fn (string $key): string => $this->urlPrefix.$key, ['sort', 'page', 'size', 'filters', 'columns', 'selected', 'confirm', 'edit', 'row']));
            $this->preserved = array_diff_key($request->query->all(), $own);
        }

        $this->filters = [...$this->blankFilters(), ...$this->filters];
        $preference = $this->preferences->load($this->name);
        $this->columns = [] === $this->columns ? $preference->columns : $this->columns;
        $this->size = 0 === $this->size ? $preference->pageSize : $this->size;

        if ('' !== $this->edit && '' !== $this->row) {
            try {
                $this->begin($this->open());
            } catch (NotFound|AccessDenied) {
                $this->stopEdit();
            }
        }
    }

    public function getView(): TableView
    {
        return $this->views->view($this->open(), new TableState(
            $this->sort,
            $this->page,
            $this->size,
            $this->filters,
            $this->columns,
            $this->selected,
            $this->confirm,
            $this->edit,
            $this->row,
            $this->urlPrefix,
            $this->preserved,
            $this->errors,
            [] === $this->errors ? null : $this->draft,
        ));
    }

    /**
     * The links of the table's export, one per format, when it offers one to the viewer.
     *
     * @return list<array{format: string, href: string}>
     */
    public function getExports(): array
    {
        return $this->exports?->links($this->open(), ['sort' => $this->sort, 'filters' => $this->filters, 'columns' => $this->columns], $this->getEncodedContext(), $this->getSeal()) ?? [];
    }

    public function getToken(): string
    {
        return $this->guard->token();
    }

    public function getEncodedContext(): string
    {
        return $this->guard->encode($this->context);
    }

    public function getSeal(): string
    {
        return $this->guard->seal($this->name, $this->getEncodedContext());
    }

    /**
     * The page to come back to after a plain form.
     */
    public function backTo(string $href): string
    {
        return $this->path.$href;
    }

    #[LiveAction]
    public function goTo(#[LiveArg] int $page): void
    {
        $this->page = $page;
        $this->selected = [];
    }

    #[LiveAction]
    public function applySort(#[LiveArg] string $value): void
    {
        $this->sort = $value;
        $this->goTo(1);
    }

    /**
     * The filters have changed: their values came with the request.
     */
    #[LiveAction]
    public function applyFilters(): void
    {
        $this->goTo(1);
    }

    #[LiveAction]
    public function clearFilters(): void
    {
        $this->filters = $this->blankFilters();
        $this->goTo(1);
    }

    /**
     * The page size has changed, its value having come with the request: the viewer keeps it.
     */
    #[LiveAction]
    public function rememberSize(#[LiveArg] string $token): void
    {
        $this->guard->assertTrusted($token);
        $this->goTo(1);
        $this->remember();
    }

    /**
     * Shows or hides a column, one always staying.
     */
    #[LiveAction]
    public function toggleColumn(#[LiveArg] string $column, #[LiveArg] string $token): void
    {
        $this->guard->assertTrusted($token);
        $this->columnsOpen = true;
        $names = array_keys($this->open()->definition->columns);

        if (\in_array($column, $names, true)) {
            $visible = [] === $this->columns ? $names : $this->columns;
            $without = array_values(array_filter($visible, static fn (mixed $name): bool => $name !== $column));
            $this->columns = match (true) {
                \count($without) === \count($visible) => array_values(array_filter($names, static fn (string $name): bool => $name === $column || \in_array($name, $visible, true))),
                [] === $without => $this->columns,
                default => $without,
            };
        }

        $this->remember();
    }

    /**
     * Selects every row of the page, or none when they all are.
     */
    #[LiveAction]
    public function toggleAll(): void
    {
        $page = array_map(static fn (RowView $row): string => $row->id, $this->getView()->rows);
        $this->selected = array_all($page, fn (string $id): bool => \in_array($id, $this->selected, true)) ? [] : $page;
    }

    /**
     * Asks about an action, or runs it when it asks for no confirmation.
     *
     * @param string|null $row the row, the selection when null
     */
    #[LiveAction]
    public function ask(#[LiveArg] string $command, #[LiveArg] string $token, #[LiveArg] ?string $row = null): void
    {
        $open = $this->open();
        $this->confirm = $command;
        $this->row = $row ?? '';
        [$declared] = $this->operations->authorize($open, $command, $this->targets());

        if (!$declared->confirms) {
            $this->perform($token);
        }
    }

    #[LiveAction]
    public function perform(#[LiveArg] string $token): void
    {
        $this->guard->assertTrusted($token);
        $open = $this->open();

        try {
            $this->operations->perform($open, $this->confirm, $this->targets());
            $this->notice = $this->translator->trans('table.action.done', [], TableOperations::DOMAIN);
            $this->selected = [];
        } catch (ActionRefused $actionRefused) {
            $this->notice = $this->translator->trans($actionRefused->getMessage(), [], $open->definition->domain);
        }

        $this->dismiss();
    }

    #[LiveAction]
    public function dismiss(): void
    {
        $this->confirm = '';
        $this->row = '';
    }

    #[LiveAction]
    public function startEdit(#[LiveArg] string $row, #[LiveArg] string $column): void
    {
        $this->row = $row;
        $this->edit = $column;
        $this->confirm = '';
        $this->begin($this->open());
    }

    #[LiveAction]
    public function save(#[LiveArg] string $token): void
    {
        $this->guard->assertTrusted($token);
        $open = $this->open();
        $outcome = $this->operations->edit($open, $this->row, $this->edit, $this->draft, $this->version);

        if ($outcome->isSaved()) {
            $this->notice = $this->translator->trans('table.edit.saved', [], TableOperations::DOMAIN);
            $this->stopEdit();
        }

        if (EditStatus::Conflict === $outcome->status) {
            $this->begin($open);
        }

        $this->errors = $outcome->errors;
    }

    #[LiveAction]
    public function stopEdit(): void
    {
        $this->edit = '';
        $this->row = '';
        $this->draft = '';
        $this->version = null;
    }

    /**
     * Every field of the filters, empty: Live Component sets the value of a field only under a key the browser was
     * given.
     *
     * @return array<string, string>
     */
    private function blankFilters(): array
    {
        $keys = [];

        foreach ($this->open()->definition->filters as $filter) {
            array_push($keys, ...(FilterType::DateRange === $filter->type ? [$filter->name.'_from', $filter->name.'_until'] : [$filter->name]));
        }

        return array_fill_keys($keys, '');
    }

    private function open(): OpenTable
    {
        return $this->tables->open($this->name, $this->context);
    }

    private function begin(OpenTable $open): void
    {
        $start = $this->operations->start($open, $this->row, $this->edit);
        $this->draft = $start->value;
        $this->version = $start->version;
    }

    /**
     * @return array<array-key, string>
     */
    private function targets(): array
    {
        return '' === $this->row ? array_filter($this->selected, \is_string(...)) : [$this->row];
    }

    private function remember(): void
    {
        $this->preferences->save($this->name, new TablePreference(array_values(array_filter($this->columns, \is_string(...))), $this->size));
    }
}
