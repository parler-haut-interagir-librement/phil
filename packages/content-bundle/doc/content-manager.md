# Writing content from PHP and from migrations

`Phil\ContentBundle\Application\ContentManager` is the PHP interface of the bundle: what the console commands
call, and what an application or a Doctrine migration calls to write content. It is a service, autowired by its
class.

```php
use Phil\ContentBundle\Application\ContentManager;

final readonly class OpenTheSite
{
    public function __construct(private ContentManager $contents)
    {
    }

    public function __invoke(): void
    {
        $news = $this->contents->create('news', 'fr', [
            'templateKey' => 'news',
            'slug' => 'opening',
            'excerptTitle' => 'Opening',
            'bodySource' => 'The site **opens** today.',
        ]);
        $this->contents->publish($news, 'fr');
        $this->contents->edit($news, 'fr', ['excerptTitle' => 'Grand opening']);   // forks version 2
        $this->contents->removeDraft($news, 'fr');                                    // back to the published draft
        $this->contents->live($news, 'fr_CA');                                        // normalized, along the chain
        $this->contents->status($news);                                               // place, slug and versions per locale
        $this->contents->delete($news);                                               // rows, routes and root
    }
}
```

## Methods

A locale is a `Locale` or its string form. Data uses the keys of the normalized form
([pipeline.md](pipeline.md#submitted-data)): an absent key changes nothing, `null` clears.

| Method | Returns | Does |
|---|---|---|
| `create(string $resourceKey, Locale\|string $locale, array $data = [])` | `ContentReference` | builds a root through `ContentRootFactory`, flushes it (a database-generated identifier exists only then), and writes its first draft in the locale, version 1, `unpublished` |
| `edit(ContentReference, Locale\|string, array $data)` | `void` | edits the draft of the locale, forking a frozen version first ([publication.md](publication.md#editing)) |
| `publish(ContentReference, Locale\|string, bool $acceptLosses = false)` | `void` | publishes the locale, then dispatches `PagePublished`; a body that loses content in UJO is refused unless `$acceptLosses` ([publication.md](publication.md#a-publication-that-loses-content-is-refused)) |
| `unpublish(ContentReference, Locale\|string)` | `void` | takes the locale offline, then dispatches `PageRetired`; its route stays |
| `removeDraft(ContentReference, Locale\|string)` | `void` | puts back the draft the locale was last published from |
| `delete(ContentReference)` | `void` | removes every row of the root in both stages and every version, its working copies, every route of it, current and history, then the root: its addresses are free again; dispatches `PageRetired` for each locale that was live |
| `draft(ContentReference, Locale\|string)` | `array<string, mixed>` | the latest draft of the locale, merged over its shared row and normalized |
| `live(ContentReference, Locale\|string)` | `array<string, mixed>` | what a reader of the locale is served, along its declared fallback chain, normalized |
| `list(string $resourceKey)` | `list<ContentReference>` | every root of the kind |
| `status(ContentReference)` | `list<ContentStatus>` | one status per locale of the latest draft version, ordered by locale; empty for a root without draft |
| `single(string $resourceKey)` | `?ContentReference` | the root of a single kind (`phil_content.singles`), `null` before it is created; a kind not declared single is an `InvalidContent` |
| `exists(ContentReference)` | `bool` | whether the root exists |
| `servedLocale(Locale\|string)` | `Locale` | the locale, once `phil_content.locales` is known to serve it |

`ContentStatus` is a readonly value with public `reference`, `locale` (string), `draftVersion` (int), `place`
(`?string`), `slug` (`?string`) and `liveVersion` (`?int`, the version readers of the locale are served).

### Errors

Every method that takes a locale refuses one that `phil_content.locales` does not serve, or that is malformed,
with `InvalidDimension`. The refusals of the domain all implement `Phil\DddBundle\Domain\Exception\DomainError`:

| Exception | When |
|---|---|
| `InvalidDimension` | a locale malformed or not served |
| `InvalidContent` | a submitted value refused, a malformed reference, a second root of a single kind |
| `PublicationLosesContent` | publishing would lose part of the body in UJO, and the losses were not accepted |
| `ContentNotFound` | no root for the reference; no draft or no live row where one is needed; nothing published along the chain |
| `SlugTaken` | the slug is the current address of another root of the same kind in the locale |
| `TransitionRefused` | the workflow does not allow the operation from where the row stands, a guard of the application blocked it, or removing the draft would discard another locale's shared changes |

An unknown resource key throws `InvalidContentMapping`, a `LogicException`: it is a mistake of the caller's code
or configuration.

## Transactions

**Each writing method runs in its own `ContentTransaction`**, nested in the caller's when one is open (a
savepoint on Doctrine). `draft()`, `live()`, `list()` and `status()` only read.

On Doctrine, a failure rolls the transaction back and clears the unit of work — the caller's entities as well,
when the operation is nested in the caller's transaction. A refusal of the domain leaves the entity manager
open; a failure of the database during a flush (a unique index refusing a concurrent publication) closes it, as
`doctrine/orm` closes it whenever a commit fails: a caller that catches the error and writes again resets the
entity manager first (`ManagerRegistry::resetManager()`). See [storage.md](storage.md#transactions).

## From a Doctrine migration

A migration receives the manager through a migration factory of the application, which decorates the one of
DoctrineMigrationsBundle:

```php
namespace App\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Version\MigrationFactory;
use Phil\ContentBundle\Application\ContentManager;

final readonly class ContentMigrationFactory implements MigrationFactory
{
    public function __construct(
        private MigrationFactory $inner,
        private ContentManager $contents,
    ) {
    }

    public function createVersion(string $migrationClassName): AbstractMigration
    {
        $migration = $this->inner->createVersion($migrationClassName);

        if ($migration instanceof ContentMigration) {
            $migration->setContentManager($this->contents);
        }

        return $migration;
    }
}
```

```yaml
# config/packages/doctrine_migrations.yaml
doctrine_migrations:
    services:
        Doctrine\Migrations\Version\MigrationFactory: App\Migrations\ContentMigrationFactory
```

`ContentMigration` is the application's own interface (a `setContentManager()` setter), implemented by the
migrations that write content:

```php
final class Version20261102120000 extends AbstractMigration implements ContentMigration
{
    private ContentManager $contents;

    public function setContentManager(ContentManager $contents): void
    {
        $this->contents = $contents;
    }

    public function up(Schema $schema): void
    {
        $about = $this->contents->create('static_page', 'fr', ['slug' => 'a-propos', 'excerptTitle' => 'À propos']);
        $this->contents->publish($about, 'fr');
    }
}
```

The manager writes through the ORM at once, while `addSql()` statements run after `up()` returns: **a migration
that writes content needs its tables created by an earlier migration**, and writes no `addSql()` of its own.
Inside the migration's transaction, each call nests in a savepoint.
