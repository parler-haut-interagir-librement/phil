<!-- phil-dev source-sha256=c31300437e1e0a5d8a802b3d8c1170315223fe8b0ac16391f42d5e6e3270892e -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Classes en lecture seule, `new` sans parenthèses |
| `phil/print-preflight`, `phil/fonts` | Les vérifications, le modèle, et les permissions d'intégration des polices |
| `symfony/config`, `symfony/dependency-injection` 8.2 | La classe du bundle, sa configuration et ses services |
| `symfony/console`, `symfony/filesystem` 8.2 | La commande, et la lecture du fichier qu'elle vérifie |
| `phil/fonts-bundle`, facultatif | Les polices d'un document mesuré nommées par famille, graisse et style |
| `phil/paged-media`, facultatif | `PdfLayout` |
| `phil/media-bundle`, facultatif | `MediaImages` |

## Installation

```sh
composer require phil/print-preflight-bundle
```

Sans Symfony Flex, activez le bundle :

```php
// config/bundles.php
return [
    // …
    Phil\PrintPreflightBundle\PhilPrintPreflightBundle::class => ['all' => true],
];
```
