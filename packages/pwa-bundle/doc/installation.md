# Installation

## Requirements

- PHP 8.5 or later, with `ext-mbstring` and `ext-uri`.
- Symfony 8.2 or later: FrameworkBundle, TwigBundle and the Routing component; Twig 3.21 or later.
- AssetMapper, which serves the service worker and the controllers, and versions the precached assets.
- The Stimulus bundle, for the controllers.
- An HTTPS origin, or `localhost` in development: browsers register service workers nowhere else.

## Installing

```sh
composer require phil/pwa-bundle
```

With Symfony Flex, the recipe registers the bundle, writes `config/packages/phil_pwa.yaml` with a manifest to complete,
and imports the routes in `config/routes/phil_pwa.yaml`. Without Flex:

```php
// config/bundles.php
Phil\PwaBundle\PhilPwaBundle::class => ['all' => true],
```

```yaml
# config/routes/phil_pwa.yaml
phil_pwa:
    resource: '@PhilPwaBundle/config/routes.php'
```

Import the routes **without a prefix**: a worker served at `/sw.js` controls the whole site. Served under a prefix,
it answers with `Service-Worker-Allowed` so its scope can still be the one configured
([The service worker](service-worker.md#headers-scope-and-csp)).

## The layout

```twig
<head>
    {{ phil_pwa_head() }}
</head>
<body {{ stimulus_controller('phil--pwa-bundle--pwa', {url: phil_pwa_service_worker_path(), scope: phil_pwa_scope()}) }}>
```

`phil_pwa_head()` writes the `<link rel="manifest">` and, with a `theme_color`, the `<meta name="theme-color">`. The
controller registers the worker ([JavaScript](javascript.md)). With AssetMapper, the bundle adds its directory
`assets/src` under the namespace `@phil/pwa-bundle`; the Stimulus bundle finds the controllers from
`assets/package.json` once the application's `assets/controllers.json` lists them, as `importmap:require` and the
recipe of a Symfony UX package do.
