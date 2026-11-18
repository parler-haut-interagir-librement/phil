# Installation

## Requirements

| Package | Version | Why |
|---|---|---|
| PHP | 8.5 or later, with `mbstring` | Titles counted in characters. |
| `doctrine/orm`, `doctrine/dbal` | `^3.7`, `^4.5` | The table of the progress. |
| `symfony/config`, `symfony/dependency-injection` | `^8.2` | The bundle, its configuration and the declarations. |
| `symfony/http-foundation`, `symfony/security-csrf` | `^8.2` | The endpoints and their stateless CSRF token. |
| `symfony/service-contracts` | `^3.6` | The locator of the checkers. |
| `psr/clock`, `psr/event-dispatcher` | `^1.0` | The time of each step; the events. |

Optional: Twig, Symfony Routing and Symfony UX Twig Component for the checklist and the tour, with the recipe `Button`
of the kit; the Stimulus bundle and AssetMapper for the tour's controller; Symfony Security for the current user;
`phil/settings-bundle` for the preference that turns the tours off; `phil/privacy-bundle` for a user's progress in the
export and the erasure of their data.

## Enabling the bundle

```sh
composer require phil/onboarding-bundle
```

```php
// config/bundles.php
return [
    // …
    Phil\OnboardingBundle\PhilOnboardingBundle::class => ['all' => true],
];
```

The bundle needs a `clock` service, which FrameworkBundle registers, and FrameworkBundle's CSRF protection, on by
default: the container stops with a message when `framework.csrf_protection` is false. The token `phil_onboarding` is
stateless, so the endpoints need no session.

## The Flex recipe

The Flex recipe of the bundle registers it in `config/bundles.php`, writes `config/packages/phil_onboarding.yaml` with no
flow, and `config/routes/phil_onboarding.yaml`, which imports the endpoints below. The recipe lives in the recipes
repository of PHIL, read once the application lists that repository in `extra.symfony.endpoint`; without it, register
the bundle and import the routes by hand.

## The table

The bundle prepends its mapping to DoctrineBundle: `phil_onboarding_progress`, one row per user and flow. Generate the
migration as for the application's own entities:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

| Column | Type | |
|---|---|---|
| `user_identifier` | string (320) | Part of the primary key: the identifier the application names the user by. |
| `flow` | string (64) | Part of the primary key. |
| `steps` | JSON | The steps completed or skipped, each with its state and time. |
| `started_at`, `updated_at` | datetime | When the flow was started, or started again, and last changed. |
| `finished_at`, `dismissed_at` | datetime, nullable | When every step was first done; when the user put the flow away. |

### Collation of the keys

**Keys compare byte by byte**, as PHP compares them. The progress of a user in a flow is read by the user's
identifier: that of `Ann` is never read, resumed or dismissed for `ann` or `ánn`. `BinaryKeysListener` gives these
fields the binary collation when the ORM loads their metadata on MySQL or MariaDB; the mapping itself stays the same
on every platform.

| Table | Columns in `utf8mb4_bin` on MySQL and MariaDB |
|---|---|
| `phil_onboarding_progress` | `user_identifier`, `flow` |

On MySQL and MariaDB, these columns take the `utf8mb4` character set and the `utf8mb4_bin` collation, given the
connection's platform: the default collation of MySQL 8, `utf8mb4_0900_ai_ci`, folds case and accents. PostgreSQL's
default collations are deterministic, equal only when the bytes are, and SQLite compares bytes: their columns keep the
default.

#### Upgrading from 0.1

The tables of a 0.1 install on MySQL or MariaDB kept the connection's collation. `doctrine:migrations:diff` writes the
migration that brings the columns above to `utf8mb4_bin`; run it with `doctrine:migrations:migrate`. The change only
makes values more distinct, so no existing row breaks a key. Rows that a case-insensitive collation had already merged
stay merged; values written from the migration on are kept apart. On PostgreSQL and SQLite the diff is empty.

## The routes

```yaml
# config/routes/phil_onboarding.yaml
phil_onboarding:
    resource: '@PhilOnboardingBundle/config/routes.php'
    prefix: /onboarding
```

| Route | Path | |
|---|---|---|
| `phil_onboarding_done` | `POST /{flow}/{step}/done` | Ticks a step done by hand. |
| `phil_onboarding_answer` | `POST /{flow}/{step}/answer` | Answers the question of a step, with the choices in `choices[]`. |
| `phil_onboarding_skip` | `POST /{flow}/{step}/skip` | Skips a step that may be skipped. |
| `phil_onboarding_dismiss` | `POST /{flow}/dismiss` | Puts the flow away. |
| `phil_onboarding_reset` | `POST /{flow}/reset` | Starts the flow again. |

Keep them behind the firewall of the members: a visitor gets a `403`.

## The recipe of the kit

The checklist and the tour draw their buttons with the recipe `Button` of the PHIL UX kit, installed in the
application by the Symfony UX Toolkit and nothing rendered from `vendor/`:

```sh
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
```

The Shadcn kit's `Button` takes the same props. The recipe is found under the components subdirectory of the
`ux_toolkit` configuration; in an environment where the Toolkit is not enabled, as production often is, repeat it in
the option `kit_subdirectory`.

## The assets

With AssetMapper, the bundle serves its Stimulus controller as `@phil/onboarding-bundle` and its stylesheet under
`phil-onboarding`. Enable the controller in `assets/controllers.json`, as any Symfony UX package:

```json
{
    "controllers": {
        "@phil/onboarding-bundle": {
            "tour": { "enabled": true, "fetch": "eager" }
        }
    }
}
```

```twig
<link rel="stylesheet" href="{{ asset('phil-onboarding/onboarding.css') }}">
```
