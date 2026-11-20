# Installation

## Requirements

PHP 8.5 or later with `ext-sodium`, Symfony 8.2 or later, Doctrine DBAL 4.5, Twig 3.21 and Symfony UX Twig Component
3.7. FrameworkBundle's CSRF protection has to be on. The pages of the moderation need SecurityBundle; without it, the
report form and the journal still answer, and nobody is a moderator. The incident mode comes with
`phil/incident-mode-bundle` 0.1.1 or later ([Incident mode](incident-mode.md)).

## The bundle

```sh
composer require phil/moderation-bundle
```

```php
// config/bundles.php
Phil\ModerationBundle\PhilModerationBundle::class => ['all' => true],
```

The recipe of the bundle registers it and writes `config/packages/phil_moderation.yaml` and
`config/routes/phil_moderation.yaml`.

## The tables

The bundle adds its five tables to the schema the ORM generates for its connection; generate the migration:

```sh
php bin/console doctrine:migrations:diff
```

### Collation of the keys

**Keys compare byte by byte**, as PHP compares them. Members and targets decide what a query reads: the notes on the
member `Ann` are never read for `ann`, the statements of reasons addressed to `Ann` never listed for `ann`, and a
report of the target `post:Abc` never closed with the reports of `post:abc`.

| Table | Columns in `utf8mb4_bin` on MySQL and MariaDB |
|---|---|
| `phil_moderation_report` | `id`, `target_type`, `target_id`, `reporter`, `decision_id` |
| `phil_moderation_decision` | `id`, `target_type`, `target_id`, `moderator`, `author` |
| `phil_moderation_note` | `id`, `member`, `author` |
| `phil_moderation_sandbox` | `member` |
| `phil_moderation_statement` | `id`, `decision_id`, `recipient`, `target_type`, `target_id` |

On MySQL and MariaDB, these columns take the `utf8mb4` character set and the `utf8mb4_bin` collation, given the
connection's platform: the default collation of MySQL 8, `utf8mb4_0900_ai_ci`, folds case and accents. PostgreSQL's
default collations are deterministic, equal only when the bytes are, and SQLite compares bytes: their columns keep the
default.

#### Upgrading from 0.2

The incident mode moves to `phil/incident-mode-bundle`: the `incident` key of the configuration, the pages of the
incidents and `phil_moderation_suspended()` go ([Upgrading from 0.2](incident-mode.md#upgrading-from-02)).

#### Upgrading from 0.1

The tables of a 0.1 install on MySQL or MariaDB kept the connection's collation. `doctrine:migrations:diff` writes the
migration that brings the columns above to `utf8mb4_bin`; run it with `doctrine:migrations:migrate`. The change only
makes values more distinct, so no existing row breaks a key. Rows that a case-insensitive collation had already merged
stay merged; values written from the migration on are kept apart. On PostgreSQL and SQLite the diff is empty.

## The routes

```yaml
# config/routes/phil_moderation.yaml
phil_moderation_admin:
    resource: '@PhilModerationBundle/config/routes/admin.php'
    prefix: /admin/moderation
phil_moderation_report:
    resource: '@PhilModerationBundle/config/routes/report.php'
    prefix: /moderation
phil_moderation_journal:
    resource: '@PhilModerationBundle/config/routes/journal.php'
    prefix: /moderation/journal
phil_moderation_statements:
    resource: '@PhilModerationBundle/config/routes/statement.php'
    prefix: /moderation/statements
```

Keep `/admin` behind the firewall, and `/moderation/statements` behind the one that knows the members. With EasyAdmin,
the pages are also grafted on every dashboard; the first import is then optional ([Pages](pages.md)).

## The recipes of the kit

The templates draw their parts with recipes of the PHIL UX kit, installed in the application by the Symfony UX
Toolkit; nothing is rendered from `vendor/`:

```sh
php bin/console ux:install alert --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install badge --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install field --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install input --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install native-select --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install table --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install textarea --kit=github.com/parler-haut-interagir-librement/ux-kit
```

The recipes have the names, props and blocks of the Shadcn kit of the Toolkit, so a site on Tailwind installs them
from `github.com/parler-haut-interagir-librement/ux-kit-shadcn` instead. They are found under the components
subdirectory of the `ux_toolkit` configuration; in an environment where the Toolkit is not enabled, repeat it in the
option `kit_subdirectory`.

## The members

The bundle designates a member by a stable identifier. The application's user class implements `ModerationMember`:

```php
use Phil\ModerationBundle\Application\Contract\ModerationMember;

final class User implements UserInterface, ModerationMember
{
    public function moderationIdentifier(): string
    {
        return $this->uuid->toRfc4122();
    }
}
```

**The user identifier is never read**: it is often an e-mail address. An application whose members are found another
way replaces `CurrentMember` ([Extension points](extension-points.md)).

## The targets

Each package that holds content registers its `ModerationTarget`; an application's own content does the same, with
autoconfiguration ([The queue](queue.md#targets)).
