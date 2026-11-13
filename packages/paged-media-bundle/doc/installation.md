# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | Readonly classes, `new` in initialisers |
| `phil/paged-media` | The native writer, the Gotenberg client, its stages and its signing ports |
| `symfony/config`, `symfony/dependency-injection` 8.2 | The bundle class, its configuration and its services |
| `symfony/http-client` 8.2, optional | The client of Gotenberg and of the vault |
| `ext-sodium` | The identifiers of the jobs and the bytes of their files |
| `symfony/messenger` 8.2, optional | `PagedMediaQueue`, the messages and their handler |
| `phil/footnotes-sidenotes`, `phil/ujo`, optional | `PrintedNotes`, the notes of a document at the foot of the printed page |
| `psr/event-dispatcher`, optional | The `PdfStored` event; Symfony's event dispatcher provides it |

## Installing

```sh
composer require phil/paged-media-bundle symfony/http-client
```

Without Symfony Flex, enable the bundle:

```php
// config/bundles.php
return [
    // …
    Phil\PagedMediaBundle\PhilPagedMediaBundle::class => ['all' => true],
];
```

## Running Gotenberg

The stages and the conversions need a Gotenberg 9, which the official image runs:

```yaml
# compose.yaml
services:
    gotenberg:
        image: gotenberg/gotenberg:9
        command: [gotenberg, --api-timeout=120s, --pdf-allow-list=^https://timestamp\.example\.com(/|$)]
```

```yaml
# config/packages/phil_paged_media.yaml
phil_paged_media:
    gotenberg:
        url: http://gotenberg:3000
        timeout: 120
```

Raise Gotenberg's `--api-timeout` with the bundle's `timeout` for long documents. Gotenberg's operator decides which
timestamp authorities, webhooks and downloads it may reach; [Configuration](configuration.md) lists the bundle's side.
