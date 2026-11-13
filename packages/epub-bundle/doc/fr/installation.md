<!-- phil-dev source-sha256=caadbeb8b681eaca4d9e8dc7dea1a8fe8ff1124f21e3de7fb9c0e72a0c5dbae8 -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Les classes readonly, `new` sans parenthèses |
| `phil/epub` | Les livres et l'écrivain EPUB |
| `symfony/config`, `symfony/dependency-injection` 8.2 | La classe du bundle, sa configuration et ses services |
| `symfony/console`, `symfony/filesystem` 8.2 | La commande, et les fichiers conservés ou écrits |
| `symfony/messenger` 8.2, facultatif | `WriteEpub` et son handler |
| `psr/event-dispatcher`, facultatif | L'événement `EpubStored` ; le répartiteur d'événements de Symfony le fournit |
| `phil/fonts-bundle`, facultatif | `EpubFonts`, lorsque ce bundle est aussi activé |
| `phil/media-bundle`, facultatif | Le stockage dans la médiathèque et `MediaAssets`, lorsque ce bundle est aussi activé |

## Installer

```sh
composer require phil/epub-bundle
```

Sans Symfony Flex, activez le bundle :

```php
// config/bundles.php
return [
    // …
    Phil\EpubBundle\PhilEpubBundle::class => ['all' => true],
];
```
