# Tables

A table is a service implementing `DataTable`. It is autoconfigured and found by its `name()`.

```php
public function configure(TableBuilder $table): void
{
    $table
        ->caption('books.caption')           // required: it names the table to a screen reader
        ->translationDomain('catalogue')     // of every label; `messages` by default
        ->identifier('id')                   // the row's property, also a field of the data source
        ->version('version')                 // for the conflicts of inline editing
        ->requires('BOOKS_VIEW')             // a voter attribute to see the table
        ->column(Column::text('title', 'books.title')->sortable())
        ->sortBy('title', SortDirection::Asc)
        ->pageSizes(25, 50, 100)             // the first by default, 500 at most
        ->maxOffset(10_000);
}
```

`configure()` runs each time the table is opened and holds nothing of a request. A mistake in a declaration throws
`InvalidDefinition` at the first render.

## Columns

| Factory | Written as |
|---|---|
| `Column::text($name, $label)` | The value as a string |
| `Column::number($name, $label, $decimals = null)` | For the locale, with these digits after the point |
| `Column::date($name, $label, $dateStyle, $timeStyle, $timezone)` | By `IntlDateFormatter` styles |
| `Column::enum($name, $label, $choices)` | The label of the value; `$choices` is a list or a backed enum |
| `Column::boolean($name, $label)` | Yes or No |
| `Column::link($name, $label, $href)` | The text, linked to what `$href` returns for the row |
| `Column::block($name, $label, $template, $block)` | A block of a template, with `row` and `value` |

A link keeps to `http`, `https`, `mailto`, `tel` and addresses of this site. Any other address is written as text.

## Cell themes

A block column is drawn by a `Twig\BlockChain`: the templates of `cell_themes` first, in their order, then the column's
own template. A theme overrides a block by defining a block of the same name; every block it leaves out comes from the
column's template. The block gets `row` and `value` in its context, with Twig's globals.

```yaml
# config/packages/phil_data_table.yaml
phil_data_table:
    cell_themes: ['tables/cells.html.twig']
```

```twig
{# templates/tables/cells.html.twig #}
{% block cover %}<img class="cover" alt="{{ row.title }}" src="{{ asset('covers/' ~ row.id ~ '.jpg') }}">{% endblock %}
```

A block found in no template stops the render with a Twig `RuntimeError`.

Each column is immutable, and its methods return a new one:

| Method | Effect |
|---|---|
| `of('author?.name')` | Reads this property path of the row instead of the column's name |
| `reading($function)` | Reads the value with a function |
| `sortable($field = null)` | Offers the sort, on this field of the data source or the column's path |
| `editable(Editing $editing)` | See [Actions and editing](actions-editing.md) |

A row is an array, read by key, or an object, read through Symfony PropertyAccess.

## Filters

| Factory | Control | In the address |
|---|---|---|
| `Filter::text($name, $label, $fields = [])` | Search field, 200 characters at most | `filters[name]` |
| `Filter::enum($name, $label, $choices, $field = null)` | List | `filters[name]` |
| `Filter::dateRange($name, $label, $field = null, $timezone = null)` | Two days, both included | `filters[name_from]`, `filters[name_until]` |
| `Filter::boolean($name, $label, $field = null)` | Any, yes or no | `filters[name]` |

A text filter looks in any of its fields. All the filters of a table apply together. A value outside what its filter
accepts is dropped, and two days in the wrong order are swapped.

## The component

```twig
<twig:PhilDataTable:Table name="books" />
<twig:PhilDataTable:Table name="items" urlPrefix="items_" :context="{owner: owner.id}" />
```

`context` reaches `DataTable::source()`, which uses it to scope the rows. It is signed, so a browser cannot change it.
Scalars only.
