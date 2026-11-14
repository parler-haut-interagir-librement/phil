# Reference

| Type | Role |
|---|---|
| `Application\Contract\DataTable` | A table of the application |
| `Application\Contract\DataSource` | Where the rows come from |
| `Application\Contract\TableAccess` | Answers the voter attributes |
| `Application\Contract\TablePreferences` | Keeps a user's choices |
| `Application\Definition\TableBuilder` | Declares a table |
| `Application\Definition\TableDefinition` | A table as declared and checked |
| `Application\Definition\Column`, `ColumnType` | A column and its kinds |
| `Application\Definition\Filter`, `FilterType` | A filter and its kinds |
| `Application\Definition\Action` | An action on rows |
| `Application\Definition\Editing` | How a column is edited |
| `Application\Definition\Choices` | The choices of an enum column or filter |
| `Application\Definition\Export` | The export a table offers: its attribute and its most rows |
| `Application\Export\ExportRows`, `ExportedRows` | The rows of an export, page by page, in their kinds |
| `Application\Query\TableQuery`, `ResultPage`, `Sort`, `SortDirection` | What is asked of a data source and what it returns |
| `Application\Query\TextCriterion`, `ChoiceCriterion`, `DateRangeCriterion`, `BooleanCriterion` | The filters as a data source reads them |
| `Application\Source\InMemoryDataSource` | Rows already in memory |
| `Application\State\TableState`, `ResolvedState`, `StateResolver` | The address, checked against the declaration |
| `Application\State\TableLinks` | The addresses of a table's states |
| `Application\State\TablePreference` | What a user chose |
| `Application\Tables`, `OpenTable` | The tables by name; a table opened for a request |
| `Application\TableOperations` | Actions and edits, with their checks |
| `Application\EditValues`, `EditStart`, `EditOutcome`, `EditStatus` | The values of an edit and how it went |
| `Application\RowValues` | Reads the values of a row |
| `Application\DenyingTableAccess`, `NoTablePreferences` | The defaults |
| `Application\View\TableViews` and the `*View` classes | The table laid out for a template |
| `Application\View\RowRenderer`, `Formats` | One render's rows, numbers and dates |
| `Application\Exception\*` | `InvalidDefinition`, `UnknownField`, `NotFound`, `AccessDenied`, `ActionRefused`, `EditConflict`, `InvalidEditValue` |
| `Infrastructure\Doctrine\DoctrineDataSource` | Rows from a QueryBuilder |
| `Infrastructure\Http\TableGuard`, `TableController` | The checks of a change; the routes of plain forms |
| `Infrastructure\Http\TableExportController`, `Infrastructure\Export\TableExports` | The export of a table; its links and checks |
| `Infrastructure\DependencyInjection\DataTablesPass`, `CsrfProtectionPass`, `KitSubdirectoryPass` | The tables by name; the CSRF protection required; the subdirectory of the recipes |
| `Infrastructure\Twig\DataTableExtension`, `KitComponents` | `phil_data_table_kit()`, the name of a recipe of the kit; `phil_data_table_cell()`, a block cell |
| `Infrastructure\Twig\CellBlocks` | The block cells drawn by a `Twig\BlockChain` of the cell themes and the column's template, kept for the request and dropped by `kernel.reset` |
| `Infrastructure\Security\AuthorizationCheckerAccess` | Symfony Security's voters |
| `Infrastructure\Settings\SettingsTablePreferences` | The preferences in phil/settings-bundle |
| `Infrastructure\Twig\Components\TableComponent` | The component `PhilDataTable:Table` |

| Route | Purpose |
|---|---|
| `phil_data_table_action` | `POST /{table}/action/{action}` |
| `phil_data_table_edit` | `POST /{table}/edit/{column}` |
| `phil_data_table_export` | `GET /{table}/export.{format}`, with phil/data-table-export |
