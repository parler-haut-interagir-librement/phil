# phil/data-table-bundle

**Data tables that sort, filter, page, act and edit in place, with or without JavaScript.**

phil/data-table-bundle draws a table of records as a Symfony UX Live Component. A table is declared in PHP: its columns,
filters, actions, default sort and page sizes. It offers sorting, text, enum, date range and boolean filters, pagination,
actions on a row or on a selection, and editing a cell in place. On a narrow screen its rows become a stacked list.

It is for a back office or a catalogue page that lists records and lets people work on them. Reach for it when the
table's state must survive a link or a reload, and when the changes must be checked on the server every time. It is not
a client-side grid. With `phil/data-table-export`, a table exports its filtered and sorted rows as CSV or XLSX.

The state of a table is in the address, so a link, a reload or a browser without JavaScript shows the same table. Every
control is a plain link or a plain form that the component takes over when it can. Rows come from the `DataSource` port,
with an in-memory adapter and a Doctrine QueryBuilder adapter. Sort and filter fields only come from what the table
declares. Actions and edits ask a voter attribute at every call and pass a stateless CSRF check.

The bundle builds on Symfony UX Live Component, Twig Component, Validator and CSRF. Its buttons are the Button recipe of
the PHIL UX kit — or of the Shadcn kit —, installed in the application by the Symfony UX Toolkit. Doctrine ORM, Symfony
Security, AssetMapper, `phil/settings-bundle` and `phil/data-table-export` are optional, and each is used when it is
installed. The stylesheet reads the tokens of PHIL UX and has values of its own without it.

## Status

| Part | State |
|---|---|
| Declaring a table: `TableBuilder`, `Column`, `Filter`, `Action`, `Editing` | Implemented and tested |
| Block cells through the application's cell themes, by `Twig\BlockChain` | Implemented and tested |
| `DataSource`, `InMemoryDataSource`, `DoctrineDataSource` | Implemented and tested |
| The state checked against the declaration, the links of each state | Implemented and tested |
| The component `PhilDataTable:Table`, its templates, stylesheet and Stimulus controller | Implemented and tested |
| Row and bulk actions, inline editing, the routes of plain forms | Implemented and tested |
| Column visibility and page size per user: `TablePreferences`, `SettingsTablePreferences`, a refusal logged | Implemented and tested |
| The question of an action in a native `<dialog>`, shown in the page without JavaScript | Implemented and tested |
| A notice when the row being edited leaves the page | Implemented and tested |
| The buttons drawn with the Button recipe of the kit | Implemented and tested |
| The export as CSV or XLSX, with `phil/data-table-export` | Implemented and tested |
| The browser suite: filters, columns, focus and the dialog in Chrome | Implemented and tested |
| The Flex recipe: the bundle, its configuration and its routes | Written in `recipe/0.1`, for the recipes repository of PHIL |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering, the recipe, routes, assets, configuration. |
| [Tables](doc/tables.md) | Declaring columns, filters, sorts and page sizes; the component. |
| [Data sources](doc/data-sources.md) | The port, the adapters, paging and its bounds. |
| [Actions and editing](doc/actions-editing.md) | Row and bulk actions, inline editing, what is checked and when. |
| [Export](doc/export.md) | The export of a table as CSV or XLSX, its route, its checks and its limits. |
| [State in the address](doc/state.md) | The parameters, several tables on a page, the fallback without JavaScript. |
| [Accessibility](doc/accessibility.md) | What the markup says, the keyboard, the stacked list. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type of the bundle. |

## Quick start

```sh
composer require phil/data-table-bundle
```

```php
final readonly class BooksTable implements DataTable
{
    public function __construct(private EntityManagerInterface $entities)
    {
    }

    public static function name(): string
    {
        return 'books';
    }

    public function configure(TableBuilder $table): void
    {
        $table->caption('books.caption')
            ->column(Column::text('title', 'books.title')->sortable())
            ->column(Column::date('published', 'books.published')->sortable())
            ->filter(Filter::text('q', 'books.search', ['title']))
            ->sortBy('title');
    }

    public function source(array $context): DataSource
    {
        return new DoctrineDataSource(
            $this->entities->createQueryBuilder()->select('b')->from(Book::class, 'b'),
            ['id' => 'b.id', 'title' => 'b.title', 'published' => 'b.published'],
        );
    }
}
```

```twig
<twig:PhilDataTable:Table name="books" />
```

## Requirements

PHP 8.5 or later with `ext-intl` and `ext-mbstring`; Symfony 8.2's config, dependency injection, HTTP foundation, HTTP
kernel, property access, routing, CSRF, translation and validator; Symfony UX Live Component 3.8.1 and Twig Component 3.8;
Twig 3.31.1; the Button recipe of the PHIL UX kit, or of the Shadcn kit. Doctrine ORM, Symfony Security, AssetMapper,
`phil/settings-bundle` and `phil/data-table-export` are optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
