# Installation

## Requirements

| Package | Version | Why |
|---|---|---|
| PHP | 8.5 or later | Readonly classes |
| `phil/timed-text` | `^0.1` | Transcripts and chapters, and WebVTT served |
| `phil/url` | `^0.1` | The addresses of files, posters and tracks; the players of YouTube and Vimeo without cookies |
| `symfony/config`, `symfony/dependency-injection`, `symfony/http-foundation` | `^8.2` | The bundle, its configuration, the route of the viewings |
| `symfony/service-contracts`, `psr/event-dispatcher`, `psr/cache` | | The reset of the memory store, `VideoProgressed`, the cache store |
| `symfony/ux-twig-component` | `^3.7`, optional | The player component |
| `phil/ux-kit` | `^0.5`, optional | The recipes Video, Image and Button, and `image_origin()`, which the player draws with |

## Enabling the bundle

```sh
composer require phil/video-bundle
```

```php
// config/bundles.php
return [
    // …
    Phil\VideoBundle\PhilVideoBundle::class => ['all' => true],
];
```

## The recipes of the kit

The player is drawn with the recipes Video — which calls the recipe Image for its figure — and Button of the PHIL UX
kit, installed by the Symfony UX Toolkit, and with `image_origin()` of the bundle of phil/ux-kit, which refuses a file
of a host the site does not list:

```sh
composer require symfony/ux-twig-component phil/ux-kit
for recipe in video image button; do
    php bin/console ux:install $recipe --kit=github.com/parler-haut-interagir-librement/ux-kit
done
```

```yaml
# config/packages/phil_ux_kit.yaml
phil_ux_kit:
    image:
        hosts: [cdn.example.com]   # the hosts of the files, posters and tracks
```

The recipes have the names, props and blocks of the Shadcn kit, so a site on Tailwind installs those of the Shadcn kit
and its extras. They are found under the components subdirectory of the `ux_toolkit` configuration; in an environment
where the Toolkit is not enabled, repeat it in `kit_subdirectory`.

## The Stimulus controller

`phil--video-bundle--player` ships in `assets/` with its stylesheet, and needs `@hotwired/stimulus` 3.

## The route of the viewings

With tracking on, import the route the player sends viewings to:

```yaml
# config/routes/phil_video.yaml
phil_video:
    resource: '@PhilVideoBundle/config/routes.php'
```

It answers `POST /_phil/video/progress`, stateless; `tracking.endpoint` is that path.

## The platforms' players

A linked video loads `https://www.youtube-nocookie.com` or `https://player.vimeo.com` in a frame once pressed: a
Content Security Policy lists them in `frame-src`.
