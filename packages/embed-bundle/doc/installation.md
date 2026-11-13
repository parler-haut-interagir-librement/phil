# Installation

```sh
composer require phil/embed-bundle
```

With Symfony Flex, the recipe registers the bundle, writes `config/packages/phil_embed.yaml` with one key read from
`EMBED_KEY`, adds that variable to `.env`, and imports the script's route under `/_embed`. Without it, register the
bundle in `config/bundles.php`:

```php
Phil\EmbedBundle\PhilEmbedBundle::class => ['all' => true],
```

and import the route:

```yaml
# config/routes/phil_embed.yaml
phil_embed:
    resource: '@PhilEmbedBundle/config/routes.php'
    prefix: /_embed
```

## The key

The recipe's value of `EMBED_KEY` is a placeholder. Give each environment its own secret of 32 bytes or more, out of
the repository — a Symfony secret, or the environment of the server:

```sh
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
php bin/console secrets:set EMBED_KEY
```

## The frame controller

With AssetMapper, the frame controller is served from the bundle as `@phil/embed-bundle/frame_controller.js`, without a
build step; Symfony Flex adds it to `assets/controllers.json` from the bundle's `assets/package.json`. The embedded
page starts it from its `<html>` element:

```twig
<html lang="{{ app.request.locale }}" {{ phil_embed_frame_attributes() }}>
```

The script other sites load is not an asset of the site: the bundle serves it under its own route, at a stable address.

## Optional packages

| Package | For |
|---|---|
| `phil/browser-security-bundle` | `frame-ancestors` written into the site's own content security policy, and `X-Frame-Options` dropped there |

## Requirements

PHP 8.5 or later with the sodium extension, Symfony 8.2 or later.
