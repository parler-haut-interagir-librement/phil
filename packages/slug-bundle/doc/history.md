# History of former slugs

When a title changes, its slug often changes with it, and every link to the old address breaks. The history keeps the
former slugs of each subject, each pointing to the subject's current slug, so that an old address can be answered
with a permanent redirect instead of a 404. It is optional and off by default: it needs `doctrine/dbal`, and a table.

## Enabling

```sh
composer require doctrine/dbal
```

```yaml
# config/packages/phil_slug.yaml
phil_slug:
    history:
        enabled: true
        connection: default   # the DBAL connection of the table
        clock: clock          # the PSR-20 clock of the recorded changes
```

`history: true` is short for `enabled: true` with the defaults. With `doctrine/orm` installed, the table
`phil_slug_history` is added to the schema the ORM generates for that connection, so `doctrine:migrations:diff` writes
the migration that creates it and `doctrine:schema:validate` does not ask to drop it. Without the ORM, create it from
`SlugHistoryTable::configure()`.

### Collation of the keys

**Keys compare byte by byte**, as PHP compares them. A scope may mix cases and a subject is any identifier: a change
of the subject `Post-7` never rewrites the history of `post-7`, and a former slug of the scope `Blog` never leads to a
subject of `blog`. Slugs are lowercase by their rule; the former slug takes the binary collation with the scope it is
keyed with.

| Table | Columns in `utf8mb4_bin` on MySQL and MariaDB |
|---|---|
| `phil_slug_history` | `scope`, `former_slug`, `subject` |

On MySQL and MariaDB, these columns take the `utf8mb4` character set and the `utf8mb4_bin` collation, given the
connection's platform: the default collation of MySQL 8, `utf8mb4_0900_ai_ci`, folds case and accents. PostgreSQL's
default collations are deterministic, equal only when the bytes are, and SQLite compares bytes: their columns keep the
default.

#### Upgrading from 0.1

The tables of a 0.1 install on MySQL or MariaDB kept the connection's collation. `doctrine:migrations:diff` writes the
migration that brings the columns above to `utf8mb4_bin`; run it with `doctrine:migrations:migrate`. The change only
makes values more distinct, so no existing row breaks a key. Rows that a case-insensitive collation had already merged
stay merged; values written from the migration on are kept apart. On PostgreSQL and SQLite the diff is empty.

The history is the service `phil_slug.history`, autowired as `Phil\SlugBundle\Application\Contract\SlugHistory`.

## Scopes and subjects

| Word | What it is | Rule |
|---|---|---|
| Scope | The space in which a slug is unique: `page`, `page:fr`, `tenant-42:product` | 1 to 100 bytes of ASCII letters, digits and `_ . : / -` |
| Subject | The identifier of what the slug names in its scope, as the application writes it: `42`, a UUID | 1 to 191 bytes of UTF-8 text without control characters |

A scope or a subject outside these rules is refused with `InvalidSlugHistory` before any query, rather than cut to fit
its column. A translated page records its changes in one scope per language, and a multi-tenant site puts the tenant
in the scope.

## Recording a change

The code that changes a slug records the change, in the same unit of work:

```php
use Phil\Slug\Slug;
use Phil\SlugBundle\Application\Contract\SlugHistory;

final readonly class RenamePage
{
    public function __construct(private SlugHistory $history) {}

    public function __invoke(Page $page, Slug $newSlug): void
    {
        $this->history->record('page:'.$page->locale, (string) $page->id, $page->slug, $newSlug);
        $page->slug = $newSlug;
    }
}
```

A change is written in one transaction of three statements, so the history never holds a chain:

1. the new slug stops being a former slug of the scope, whichever subject had it: an address that is live again is
   no longer redirected;
2. every former slug of the subject is pointed to the new slug;
3. the old slug is written as a former slug of the subject, replacing any row it had.

| Changes of subject 7 in `page` | Former slugs afterwards |
|---|---|
| `a` → `b` | `a` → `b` |
| then `b` → `c` | `a` → `c`, `b` → `c` |
| then `c` → `a` | `b` → `a`, `c` → `a` |

Recording a slug as its own former slug changes nothing. The history grows by at most one row per distinct former slug
of a subject, whatever the number of changes.

## Reading it

| Method | Returns |
|---|---|
| `currentSlug(string $scope, Slug $former): ?Slug` | The current slug of the subject that once had `$former`, or `null` |
| `formerSlugs(string $scope): iterable<string, Slug>` | Every former slug of the scope, keyed by the former slug, with the current slug it points to, ordered by former slug |
| `forget(string $scope, string $subject): void` | Removes every former slug of the subject: it was deleted, or its old addresses should no longer answer |

A controller that finds no page for a slug can ask `currentSlug()` and redirect with a 301; a redirect provider reads
`formerSlugs()` to build its table. A row edited by hand into something that is not a slug is not answered.

The history keeps nothing between two calls, so it is safe in a long-running worker.
