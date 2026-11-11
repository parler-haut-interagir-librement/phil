# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\SlugBundle\PhilSlugBundle` | `AbstractBundle` | The `phil_slug` configuration and the services |
| `Phil\SlugBundle\Infrastructure\DependencyInjection\SluggerServices` | `final class` | Registers the named sluggers, their aliases and `Sluggers` |
| `Phil\SlugBundle\Infrastructure\Twig\SlugExtension` | Twig runtime | The `phil_slug` filter |
| `Phil\SlugBundle\Application\Contract\SlugHistory` | interface | The history of former slugs: `record()`, `currentSlug()`, `formerSlugs()`, `forget()` |
| `Phil\SlugBundle\Domain\SlugHistoryKeys` | `final class` | The rules of a scope and a subject: `scope()`, `subject()` |
| `Phil\SlugBundle\Domain\Exception\InvalidSlugHistory` | exception | A scope or a subject the history cannot hold; an `InvalidArgumentException` |
| `Phil\SlugBundle\Infrastructure\Doctrine\DoctrineSlugHistory` | `final readonly class` | The history on a DBAL connection |
| `Phil\SlugBundle\Infrastructure\Doctrine\SlugHistoryTable` | `final class` | The table `phil_slug_history`: `configure(Schema)` |
| `Phil\SlugBundle\Infrastructure\Doctrine\SlugHistorySchemaListener` | listener | Adds the table to the schema the ORM generates |

## `SluggerServices`

| Constant | Value |
|---|---|
| `ID_PREFIX` | `phil_slug.slugger.` |
| `SLUGGERS_ID` | `Phil\Slug\Sluggers` |
| `MAX_LENGTH` | `255`, the longest slug |

## `PhilSlugBundle`

| Constant | Value |
|---|---|
| `HISTORY_ID` | `phil_slug.history`, the Doctrine history, aliased as `SlugHistory` |

## `SlugHistoryKeys`

| Constant | Value |
|---|---|
| `SCOPE_MAX_LENGTH` | `100` bytes |
| `SUBJECT_MAX_LENGTH` | `191` bytes |

## `SlugHistoryTable`

| Column | Type |
|---|---|
| `scope` | string(100), primary key with `former_slug` |
| `former_slug` | string(255) |
| `subject` | string(191), indexed with `scope` as `phil_slug_history_subject` |
| `current_slug` | string(255) |
| `recorded_at` | datetime_immutable |

The types of slugs and sluggers are `phil/slug`'s: see its reference.
