# Testing

## How the bundle is tested

Unit tests under `tests/Unit/`, namespace `Phil\AuditViewer\Tests\`, PHPUnit 13, `#[CoversClass]` on each test class.

| Test | What it proves |
|---|---|
| `Infrastructure\ControllerTest` | Each page over templates that print their context: the attribute it asks for, the page read with the filters, `400` and `404` where they belong, the response private and not stored; the export refused without its token or with filters that do not read, recorded before it is written, at most its limit; an access policy that narrows by row and by site holding on the log list, the chain list and the export, each row and segment asked for with itself |
| `Infrastructure\FiltersAndCsvTest` | Every filter read, the period in the viewer's time zone, each refusal; the CSV columns, its limit, the cells written after a `'` |
| `Infrastructure\AccessTest` | Each attribute granted to its role only, the history to its own role and the viewer's, with the reasons; the access policy narrowing the roles under the `affirmative`, `consensus` and `unanimous` strategies, asked only once a role holds, and its reason; the menu's routes, icons and labels |
| `PhilAuditViewerBundleTest` | The services and their arguments with the defaults and with every key; each value out of bounds refused |

A functional suite under `tests/Functional/` boots an EasyAdmin back office, `tests/Functional/App/TestKernel.php`:
SecurityBundle with a session firewall over four accounts, TwigBundle with the Twig components and extras,
DoctrineBundle on an SQLite file in the kernel's cache directory with an audited order and a note that is not,
EasyAdmin with a dashboard at `/admin` and their CRUD controllers, and `phil/audit-bundle` with a JSON serialization
and its reader. `AuditViewerTest` follows the pages as an auditor reads them: the log refused without the role,
filtered and paged; a row and the history of its record; the "History" action on the order's pages and not on the
note's, kept by a controller that names its own, shown by role, and the history a clerk reads without the log; the
chain verified, then a row changed in the table and the segment broken; the chain grouped by site and the log filtered
on one tenant; an export recorded then written; and the menu. It covers `AuditHistoryActions`.

## Fuzzing

The fuzz target `log-filters` gives the filters of the log query strings nobody wrote, from the seeds of
`tests/Fuzz/corpus/log-filters`: they read into a query and no refusal, or into refusals of the form's fields and no
query, and a field given as a list is refused by the request; nothing else is thrown. From the monorepo root:

```bash
composer fuzz -- log-filters
```

### From a monorepo root that installs the bundle by path

```bash
vendor/bin/phpunit packages/audit-viewer/tests
```

### Installed on its own

```bash
cd audit-viewer
composer install
vendor/bin/phpunit --no-configuration tests
```

The package ships no `phpunit.xml`. `phil/audit-bundle` and `phil/ddd-bundle` must be resolvable, from a `path` or
`vcs` repository until they are published.
