<!-- phil-dev source-sha256=c71d0bf246333624ac84c89e4be5daf019645d4705af1dd8fb632ed68aa20433 -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Les classes en lecture seule, `new` dans les initialiseurs |
| `phil/paged-media` | Le moteur d'écriture natif, le client de Gotenberg, ses étapes et ses ports de signature |
| `symfony/config`, `symfony/dependency-injection` 8.2 | La classe du bundle, sa configuration et ses services |
| `symfony/http-client` 8.2, facultatif | Le client de Gotenberg et du coffre |
| `ext-sodium` | Les identifiants des tâches et les octets de leurs fichiers |
| `symfony/messenger` 8.2, facultatif | `PagedMediaQueue`, les messages et leur *handler* |
| `phil/footnotes-sidenotes`, `phil/ujo`, facultatifs | `PrintedNotes`, les notes d'un document en bas de la page imprimée |
| `psr/event-dispatcher`, facultatif | L'événement `PdfStored` ; le répartiteur d'événements de Symfony le fournit |

## Installer

```sh
composer require phil/paged-media-bundle symfony/http-client
```

Sans Symfony Flex, activez le bundle :

```php
// config/bundles.php
return [
    // …
    Phil\PagedMediaBundle\PhilPagedMediaBundle::class => ['all' => true],
];
```

## Exécuter Gotenberg

Les étapes et les conversions ont besoin d'un Gotenberg 9, que l'image officielle exécute :

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

Augmentez le `--api-timeout` de Gotenberg en même temps que le `timeout` du bundle pour les longs documents.
L'exploitant de Gotenberg décide quelles autorités d'horodatage, quels *webhooks* et quels téléchargements il peut
atteindre ; [Configuration](configuration.md) présente le côté du bundle.
