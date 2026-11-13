# phil/pwa-bundle

**An installable site that reads offline: a manifest generated from the configuration, a short service worker of its
own served through AssetMapper, an offline page, and Stimulus controllers to install, update, go full screen and
present on a second screen — no Workbox, no build step.**

phil/pwa-bundle serves the **web application manifest** of the site at `/manifest.webmanifest`, written from the
configuration: names, identity, start and scope, `display` and `display_override` — full screen included —, colours,
language, icons versioned by AssetMapper, shortcuts and categories. Every member is checked when the container is
built.

It serves the **service worker** at `/sw.js`: a few lines of settings, then the code of the worker, a script of the
bundle served by AssetMapper at its versioned address. The worker caches a **versioned precache list** when it installs
— the offline page, the paths and assets of the configuration, those of other bundles —, deletes the caches of older
versions when it activates, answers navigations **network-first** with the **offline page** as a last resort, and
other requests **cache-first**, network-first or from the network alone by the prefix of their path. A page whose
`Cache-Control` says `private` or `no-store` is never kept.

A **Stimulus controller** registers the worker, offers to install the site when the browser does, offers to update it
when a new worker waits, follows the connection, and enters or leaves the full screen. Another opens a page on a
**second screen** with the Presentation API, or in a window of its own where the API is missing, and carries messages
between both.

It is a Symfony bundle for AssetMapper and the Stimulus bundle; Webpack Encore is not supported.

## Status

| Part | State |
|---|---|
| Manifest generated and checked from the configuration, icons versioned through the Asset component | Implemented and tested |
| Service worker of its own: precache, network-first and cache-first, offline page, versions, skip waiting | Implemented and tested |
| `/sw.js` with its settings and `importScripts()` of the worker served by AssetMapper; safe headers and scope | Implemented and tested |
| Precache providers for other bundles, by autoconfiguration | Implemented and tested |
| Offline page and `phil_pwa_head()` | Implemented and tested |
| Stimulus controller: register, install, update, connection, full screen | Implemented and tested |
| Stimulus controller: presentation on a second screen, with a fallback window | Implemented and tested |
| Fuzz target of the manifest reader | Implemented and tested |
| Flex recipe | Implemented |
| Browser test of installing and of reading offline, under Panther | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the layout and the controllers. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [The manifest](doc/manifest.md) | Each member, what is checked, icons and shortcuts. |
| [The service worker](doc/service-worker.md) | Precache, strategies, offline, versions, headers, scope and CSP. |
| [JavaScript](doc/javascript.md) | The Stimulus controllers: installing, updating, full screen, a second screen. |
| [Integrations](doc/integrations.md) | What signage, an event programme or a kiosk builds on. |
| [Extension points](doc/extension-points.md) | The services to replace and when they are stable. |
| [Testing](doc/testing.md) | The bundle's PHP and JavaScript tests and its fuzz target. |
| [Reference](doc/reference.md) | Every class, service, route and Twig function of the bundle. |

## Quick start

```sh
composer require phil/pwa-bundle
```

```yaml
# config/packages/phil_pwa.yaml
phil_pwa:
    manifest:
        name: Example
        short_name: Example
        theme_color: '#1b1b1b'
        background_color: '#ffffff'
        icons:
            - { src: images/icon-192.png, sizes: 192x192, type: image/png }
            - { src: images/icon-512.png, sizes: 512x512, type: image/png, purpose: [any, maskable] }
    service_worker:
        precache: [styles/app.css, /]
```

```twig
{# templates/base.html.twig #}
<head>
    {{ phil_pwa_head() }}
</head>
<body {{ stimulus_controller('phil--pwa-bundle--pwa', {url: phil_pwa_service_worker_path(), scope: phil_pwa_scope()}) }}>
```

## Requirements

PHP 8.5 or later with `ext-mbstring` and `ext-uri`, Symfony 8.2 or later and Twig 3.21; AssetMapper and the Stimulus
bundle for the worker and the controllers.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
