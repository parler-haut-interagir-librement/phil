# Installation

## Requirements

- PHP 8.5 or later, with `ext-dom` and `ext-mbstring`.
- Symfony 8.2 or later: FrameworkBundle, TwigBundle and the Routing component.
- Twig 3.21 or later and Symfony UX Twig Component 3.7 or later.
- The recipes `card` and `badge` of the PHIL UX kit, or of the Shadcn kit, installed by the Symfony UX Toolkit.

Optional:

| Package | For |
|---|---|
| `phil/monitoring-bundle` | The states of the components read from its checks and scheduled tasks. |
| `symfony/cache` | The states read from the checks kept for a while. |
| `league/flysystem` | A `flysystem` target. |
| `symfony/console` | `phil:status-page:push`. |
| `symfony/messenger`, `symfony/scheduler` | A snapshot pushed in a worker, at a regular interval. |
| `symfony/translation` | The page in English and French. |

## Installing

```sh
composer require phil/status-page-bundle
```

With Symfony Flex, the recipe registers the bundle, writes `config/packages/phil_status_page.yaml` with one component,
and imports the routes in `config/routes/phil_status_page.yaml`. Without Flex:

```php
// config/bundles.php
Phil\StatusPageBundle\PhilStatusPageBundle::class => ['all' => true],
```

```yaml
# config/routes/phil_status_page.yaml
phil_status_page:
    resource: '@PhilStatusPageBundle/config/routes.php'
```

The routes are `/status`, `/status.json` and `/status.atom`; a prefix or a host is the application's, given on the
import.

## The recipes of the kit

The templates draw their parts with recipes of the PHIL UX kit, installed in the application; nothing is rendered from
`vendor/`:

```sh
php bin/console ux:install card --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install badge --kit=github.com/parler-haut-interagir-librement/ux-kit
```

They have the names, props and blocks of the Shadcn kit of the Toolkit, so a site on Tailwind installs them from the
Shadcn kit instead. They are found under the components subdirectory of the `ux_toolkit` configuration; in an
environment where the Toolkit is not enabled, as production often is, repeat it in `kit_subdirectory`.
