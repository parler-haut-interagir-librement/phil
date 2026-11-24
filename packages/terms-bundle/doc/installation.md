# Installation

## Requirements

| Requirement | Version | Why |
|---|---|---|
| PHP, `ext-mbstring`, `ext-filter` | 8.5 or later | `readonly` classes, typed class constants, `#[Override]`, `array_find()` and `array_any()`; an email address told from a member identifier. |
| `doctrine/dbal` | `^4.5` | The two tables, built with the schema editors of DBAL 4.5. |
| `psr/clock`, `psr/event-dispatcher` | `^1.0` | The clock of every moment; the events of a publication and of an acceptance. |
| `symfony/config`, `dependency-injection`, `http-foundation`, `http-kernel`, `routing` | `^8.2` | Configuration, wiring, the gate, the pages and their routes. |
| `symfony/security-core`, `symfony/security-csrf` | `^8.2` | The signed-in member, the role of the administration, the token of the forms. |
| `symfony/service-contracts`, `symfony/translation-contracts` | `^3.6` | `kernel.reset`; the messages of the gate and of the forms. |
| `twig/twig` | `^3.21` | The acceptance page, the public pages and the administration. |
| `symfony/ux-twig-component` | `^3.7` | The recipes of the kit the templates draw. |

Optional packages, each enabling one part:

| Package | Enables |
|---|---|
| `doctrine/doctrine-bundle` | The `doctrine.dbal.<name>_connection` service and the schema listener's tag. In practice always there. |
| `doctrine/orm` | The two tables added to the schema the ORM generates, for `doctrine:migrations:diff`. The bundle itself reads and writes through DBAL only. |
| `symfony/translation` | The pages in the request's language, English and French included. |
| `symfony/form` | The `terms_acceptance` option of a signup form. |
| `symfony/console` | `phil:terms:outstanding`. |
| `symfony/scheduler`, `symfony/messenger` | The reminders. |
| `phil/notifications-bundle` | The notices of a new version and the reminders. |
| `phil/legal-notices-bundle` | `phil:terms:check-legal-links`, the check that the legal links exist on the site. |
| `phil/ip-anonymizer` | The network an acceptance came from. |
| `phil/privacy-bundle` | The acceptances in the export and the erasure of a person's data. |
| `phil/text-diff` | The changes between two versions on the public pages. |
| `phil/ujo` | Texts written as UJO (Universal JSON Output) documents. |
| `easycorp/easyadmin-bundle` | The link of the administration in an EasyAdmin menu. |

## Install with Composer

```sh
composer require phil/terms-bundle
```

## Register the bundle

```php
// config/bundles.php
return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Symfony\UX\TwigComponent\TwigComponentBundle::class => ['all' => true],
    Phil\TermsBundle\PhilTermsBundle::class => ['all' => true],
];
```

## Create the tables

With the ORM, the bundle adds `phil_terms_version` and `phil_terms_acceptance` to the schema the ORM generates for its
connection; the application's next migration creates them:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Without the ORM, create them in a migration of the application with `Infrastructure\Doctrine\TermsTables::configure()`,
which adds both tables to a DBAL `Schema`.

### Collation of the keys

**Keys compare byte by byte**, as PHP compares them. The gate asks on every request whether a member accepted a
policy's version: an acceptance by the member `Ann` never lets `ann` or `ánn` through, and the export of a member's
data never holds another's acceptances.

| Table | Columns in `utf8mb4_bin` on MySQL and MariaDB |
|---|---|
| `phil_terms_version` | `policy` |
| `phil_terms_acceptance` | `member`, `policy` |

On MySQL and MariaDB, these columns take the `utf8mb4` character set and the `utf8mb4_bin` collation, given the
connection's platform: the default collation of MySQL 8, `utf8mb4_0900_ai_ci`, folds case and accents. PostgreSQL's
default collations are deterministic, equal only when the bytes are, and SQLite compares bytes: their columns keep the
default.

#### Upgrading from 0.1

The tables of a 0.1 install on MySQL or MariaDB kept the connection's collation. `doctrine:migrations:diff` writes the
migration that brings the columns above to `utf8mb4_bin`; run it with `doctrine:migrations:migrate`. The change only
makes values more distinct, so no existing row breaks a key. Rows that a case-insensitive collation had already merged
stay merged; values written from the migration on are kept apart. On PostgreSQL and SQLite the diff is empty.

#### Upgrading from 0.2

`phil_terms_acceptance` has two more columns, `locale` and `text_sha256`, the proof of the text each acceptance was
shown ([Acceptance](acceptance.md#proving-the-text-accepted)). `doctrine:migrations:diff` writes the migration; on
MySQL it reads:

```sql
ALTER TABLE phil_terms_acceptance ADD locale VARCHAR(35) DEFAULT NULL, ADD text_sha256 CHAR(64) DEFAULT NULL;
```

Both columns are nullable: the acceptances recorded before the migration keep `NULL` in them and read back without a
proof, and every acceptance recorded after it has both. Nothing is filled in afterwards: a text accepted in the past
cannot be told from the version alone, since the locale the member read was not kept.

## Install the recipe

The Flex recipe of the bundle registers it, writes `config/packages/phil_terms.yaml` with the policies `terms` and
`privacy`, imports the public pages in `config/routes/phil_terms.yaml`, and copies `App\Terms\Members` and
`App\Terms\CurrentMember` to `src/Terms/`. Until those two read the application's accounts, they list and name nobody:
the gate asks no one, and the application boots. The recipe lives in the recipes repository of PHIL, read once the
application lists that repository in `extra.symfony.endpoint`; without it, register the bundle and write the
configuration by hand, as below.

## Install the recipes of the kit

The pages draw their parts with recipes of the PHIL UX kit, installed in the application by the Symfony UX Toolkit:

```sh
for recipe in alert badge button checkbox field input native-select table textarea; do
    php bin/console ux:install $recipe --kit=github.com/parler-haut-interagir-librement/ux-kit
done
```

A site on Tailwind installs the same nine recipes from the Shadcn kit of the Toolkit instead (`--kit=shadcn`), with
`label` and `separator`, which its recipes call, and Symfony UX Icons. `PhilTermsBundle::RECIPES` lists them. The
recipes are found in the components subdirectory of the Toolkit's configuration, or in `kit_subdirectory`
([Configuration](configuration.md)).

## Mount the routes

The gate sends a member to the acceptance page, and the problem of an API links to the versions: the routes of the
pages are always mounted. The administration is mounted behind the firewall, when it is enabled.

```yaml
# config/routes/phil_terms.yaml
phil_terms:
    resource: '@PhilTermsBundle/config/routes/pages.php'
    prefix: /terms

phil_terms_admin:
    resource: '@PhilTermsBundle/config/routes/admin.php'
    prefix: /admin/terms
```

The acceptance page is at `<prefix>/accept`, so no policy is named `accept`.

## Declare the policies

```yaml
# config/packages/phil_terms.yaml
phil_terms:
    locales: [en, fr]
    policies:
        terms: ~
        privacy: { renew_after_days: 365 }
        code_of_conduct: { required: false }
    member_source: App\Security\EveryMember
    member_resolver: App\Security\MemberId
```

## Name the members

`member_source` is required: the container is not built without it. It names a service that implements
`Application\Contract\MemberSource` and gives every member of the site, those who never accepted anything included,
so that `Outstanding` and the reminders reach them all.

```php
use Phil\TermsBundle\Application\Contract\MemberSource;

final readonly class EveryMember implements MemberSource
{
    public function __construct(private MemberRepository $members) {}

    public function members(): iterable
    {
        yield from $this->members->identifiers();
    }
}
```

A member is named by a stable identifier, never by personal information. `member_resolver` names a service that
implements `Application\Contract\MemberResolver` and gives that identifier; [Configuration](configuration.md#the-member)
shows one. Without it, the member is the user identifier of the security token, and the build is refused unless
`stable_identifiers: true` declares that those identifiers are never email addresses; `TokenMemberResolver` still
throws a `LogicException` on one shaped like an email address.

Then publish a first version of each, from the administration or from code: a policy without a version asks nothing.
