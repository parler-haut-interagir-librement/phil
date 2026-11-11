<!-- phil-dev source-sha256=bf317d35d38413e1f6eaf2f3d52b6608e8effe0517a319b1e7504c34747a342f -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `Phil\SlugBundle\PhilSlugBundle` | `AbstractBundle` | La configuration `phil_slug` et les services |
| `Phil\SlugBundle\Infrastructure\DependencyInjection\SluggerServices` | `final class` | Enregistre les générateurs nommés, leurs alias et `Sluggers` |
| `Phil\SlugBundle\Infrastructure\Twig\SlugExtension` | runtime Twig | Le filtre `phil_slug` |
| `Phil\SlugBundle\Application\Contract\SlugHistory` | interface | L'historique des anciens slugs : `record()`, `currentSlug()`, `formerSlugs()`, `forget()` |
| `Phil\SlugBundle\Domain\SlugHistoryKeys` | `final class` | Les règles d'une portée et d'un sujet : `scope()`, `subject()` |
| `Phil\SlugBundle\Domain\Exception\InvalidSlugHistory` | exception | Une portée ou un sujet que l'historique ne peut pas garder ; une `InvalidArgumentException` |
| `Phil\SlugBundle\Infrastructure\Doctrine\DoctrineSlugHistory` | `final readonly class` | L'historique sur une connexion DBAL |
| `Phil\SlugBundle\Infrastructure\Doctrine\SlugHistoryTable` | `final class` | La table `phil_slug_history` : `configure(Schema)` |
| `Phil\SlugBundle\Infrastructure\Doctrine\SlugHistorySchemaListener` | écouteur | Ajoute la table au schéma que l'ORM génère |

## `SluggerServices`

| Constante | Valeur |
|---|---|
| `ID_PREFIX` | `phil_slug.slugger.` |
| `SLUGGERS_ID` | `Phil\Slug\Sluggers` |
| `MAX_LENGTH` | `255`, le slug le plus long |

## `PhilSlugBundle`

| Constante | Valeur |
|---|---|
| `HISTORY_ID` | `phil_slug.history`, l'historique Doctrine, avec l'alias `SlugHistory` |

## `SlugHistoryKeys`

| Constante | Valeur |
|---|---|
| `SCOPE_MAX_LENGTH` | `100` octets |
| `SUBJECT_MAX_LENGTH` | `191` octets |

## `SlugHistoryTable`

| Colonne | Type |
|---|---|
| `scope` | string(100), clé primaire avec `former_slug` |
| `former_slug` | string(255) |
| `subject` | string(191), indexé avec `scope` sous `phil_slug_history_subject` |
| `current_slug` | string(255) |
| `recorded_at` | datetime_immutable |

Les types de slugs et de générateurs sont ceux de `phil/slug` : voyez sa référence.
