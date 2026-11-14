# Extension points

Every port lives in `Phil\DataTableBundle\Application\Contract`. Until the first release, each may still change; it is
stable from 1.0.

## `DataTable`

A table of the application. See [Tables](tables.md). The service is autoconfigured with the tag `phil_data_table.table`.

## `DataSource`

Where the rows come from. See [Data sources](data-sources.md).

## `TableAccess`

Whether the viewer holds a voter attribute on a subject. Replace it with `phil_data_table.access`.

```php
interface TableAccess
{
    public function isGranted(string $attribute, array|object|null $subject = null): bool;
}
```

Symfony Security answers by default when it is installed. Otherwise every attribute is refused.

## `TablePreferences`

The columns and the page size a user keeps, table by table. Replace it with `phil_data_table.preferences`.

```php
interface TablePreferences
{
    public function load(string $table): TablePreference;

    public function save(string $table, TablePreference $preference): void;
}
```

By default nothing is kept. With phil/settings-bundle, `SettingsTablePreferences` keeps them in the user setting
`data_table.preferences`, and a visitor keeps theirs in the address only.

## Templates and styles

Override any template under `templates/bundles/PhilDataTableBundle/`. A column's own template and block draw a cell with
`Column::block()`, and the templates of `cell_themes` override its blocks one by one: see
[Cell themes](tables.md#cell-themes). The stylesheet is in the `phil.components` layer, so styles outside any layer win.

## Worker mode

The services keep nothing between two requests. A table's implementation does the same: its context comes as an argument.
