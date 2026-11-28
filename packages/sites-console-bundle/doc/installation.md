# Installation

## Requirements

- PHP 8.5 or later, with `ext-sodium` and `ext-mbstring`.
- Symfony 8.2 or later: FrameworkBundle with the Lock component, and the Routing component.

On the console, also:

- SecurityBundle, with a firewall in front of the screens whose users carry the roles of the console, and CSRF
  protection.
- TwigBundle, Twig 3.31.1 or later and Symfony UX Twig Component 3.8 or later.
- The HttpClient component.
- DoctrineBundle with the ORM, for the tables of the console.
- The recipes `badge`, `button`, `card` and `table` of the PHIL UX kit, or of the Shadcn kit, installed by the
  Symfony UX Toolkit.

Optional:

| Package | For |
|---|---|
| `phil/monitoring-bundle` | The health of each site, read from its checks. |
| `symfony/messenger` | Pulls, pushes and update plans handed to a worker from the screens. |
| `symfony/translation` | The screens in English and French. |

## Installing

```sh
composer require phil/sites-console-bundle
```

With Symfony Flex, the recipe registers the bundle and writes `config/packages/phil_sites_console.yaml` and
`config/routes/phil_sites_console.yaml`, both halves commented out. Without Flex:

```php
// config/bundles.php
Phil\SitesConsoleBundle\PhilSitesConsoleBundle::class => ['all' => true],
```

## Keys

Each side has its own Ed25519 key pair. Generate one for the console and one for each site:

```sh
php bin/console phil:sites-console:keys
```

The command prints a private key and its public key and writes nothing. Keep each private key in an environment
variable or a secret of its own application; give the console's public key to every site, and each site's public key
to the console when the site is registered.

## On each site: the agent

```yaml
# config/packages/phil_sites_console.yaml
phil_sites_console:
    agent:
        enabled: true
        site_id: shop
        private_key: '%env(SITES_CONSOLE_AGENT_KEY)%'
        console_public_key: '%env(SITES_CONSOLE_PUBLIC_KEY)%'
```

```yaml
# config/routes/phil_sites_console.yaml
phil_sites_console_agent:
    resource: '@PhilSitesConsoleBundle/config/routes/agent.php'
    prefix: /_sites-console
```

The endpoints answer the console only, by signature: they need no firewall and no session. Keep them out of any
firewall that would ask for a login. The agent keeps its nonces and idempotency keys in `cache.app` by default: on a
site with several servers, name a pool they share in `agent.cache_pool`, and a lock factory on a store they share in
`agent.lock_factory`. The console keys a rotation stages and
retires go to `agent.key_pool`, or to that pool: choose one that survives deploys, such as Redis or a database.

## On the console

```yaml
# config/packages/phil_sites_console.yaml
phil_sites_console:
    console:
        enabled: true
        private_key: '%env(SITES_CONSOLE_PRIVATE_KEY)%'
```

```yaml
# config/routes/phil_sites_console.yaml
phil_sites_console:
    resource: '@PhilSitesConsoleBundle/config/routes/console.php'
    prefix: /admin/sites
```

Put the prefix behind a firewall. The screens ask for `ROLE_SITES_CONSOLE_VIEWER` to read and
`ROLE_SITES_CONSOLE_OPERATOR` to act; the firewall gives them from the identity provider — the roles claim of an
OpenID Connect token mapped by the application's authenticator, for instance. See
[Security model](security-model.md#who-may-read-and-act).

### The tables

The bundle maps its six tables for Doctrine when the console is on with its Doctrine store: `phil_sites_console_site`,
`phil_sites_console_snapshot`, `phil_sites_console_push`, `phil_sites_console_update_plan`, `phil_sites_console_journal`
and `phil_sites_console_key_rotation`. Generate the migration for MySQL, MariaDB or PostgreSQL with:

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

### The recipes of the kit

The screens draw their parts with recipes of the PHIL UX kit, installed in the application; nothing is rendered from
`vendor/`:

```sh
php bin/console ux:install badge --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install card --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install table --kit=github.com/parler-haut-interagir-librement/ux-kit
```

They have the names, props and blocks of the Shadcn kit of the Toolkit, so a site on Tailwind installs them from the
Shadcn kit instead. They are found under the components subdirectory of the `ux_toolkit` configuration; in an
environment where the Toolkit is not enabled, as production often is, repeat it in `console.kit_subdirectory`.

## Both in one application

A console that is also a site turns both halves on and imports both route files.
