# Testing

```sh
vendor/bin/phpunit packages/data-table-bundle/tests
cd packages/data-table-bundle && node --import ./tests/js/register.mjs --test tests/js
```

| Suite | What it covers |
|---|---|
| `tests/Unit` | The declarations, the state, the views, the operations, the rows of an export, the adapters on SQLite in memory, the guard, the controller, the component, the recipes' names and the logged refusal of a preference, without a kernel |
| `tests/Functional` | A kernel with a page of two tables: plain GET state, a block cell drawn by the cell theme, the Live Component through its route, plain forms and the dialog, the notice of an edit that left the page, the export and its refusals, CSRF and voters |
| `tests/js` | The Stimulus controller under Node's test runner |
| `tests/Benchmark` | 100 rows of 10 columns laid out in PHP, and drawn by the whole component |
| `tests/Browser` | The page of the functional application in a real Chrome, served by PHP's built-in server: a filter typed and submitted, a column hidden, a sort, the focus given back after each, and the dialog of an action, modal, dismissed by Escape, the focus back on its button |

The functional application installs the Button recipe of the PHIL UX kit where `ux:install` puts it, in the
subdirectory `ui` of its components, and checks the export as CSV and XLSX with phil/data-table-export.

The browser suite runs apart, like Infection, with the `chromedriver` of the system and the Stimulus of its own
`package.json`, from the monorepo root:

```sh
composer test:browser
```

Without the Stimulus of `tests/Browser/node_modules`, as when the package is installed on its own, the browser suite is
skipped rather than failed.

In an application's own tests, `InMemoryDataSource` stands in for a query, and a `TableAccess` of the test answers the voter
attributes.
