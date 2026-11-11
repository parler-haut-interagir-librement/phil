<!-- phil-dev source-sha256=e77b0b4cbc5da5289387e263197ab3446be94aa6b6a782d6ea6dd33185f86d81 -->
# Installation

## Prérequis

| Prérequis | Raison |
|---|---|
| PHP 8.5 ou plus récent | Comme `phil/highlight` |
| `phil/highlight` | Le coloriseur et ses langages |
| `symfony/config`, `symfony/dependency-injection` 8.2 | La configuration et les services |
| `twig/twig` 3.21, facultatif | Le filtre `highlight` |
| `phil/ujo-bundle`, facultatif | Les blocs de code des documents UJO colorés sur le serveur |
| `symfony/asset-mapper` 8.2, facultatif | La feuille de style du thème, déclarée sous le nom `phil-highlight/highlight.css` |

## Installer

```sh
composer require phil/highlight-bundle
```

Symfony Flex active le bundle ; sans Flex, ajoutez-le à `config/bundles.php` :

```php
return [
    // ...
    Phil\HighlightBundle\PhilHighlightBundle::class => ['all' => true],
];
```

Sans configuration, le coloriseur lit chaque langage que fournit `phil/highlight`, et les blocs de code des documents
UJO sont colorés quand `phil/ujo-bundle` est installé.

## La feuille de style

Avec AssetMapper, le bundle déclare son dossier `assets/styles` sous le nom `phil-highlight/` ; une mise en page lie le
thème :

```twig
<link rel="stylesheet" href="{{ asset('phil-highlight/highlight.css') }}">
```

Avec une autre chaîne, importez `vendor/phil/highlight-bundle/assets/styles/highlight.css`. Les thèmes de
`phil/highlight`, dans `vendor/phil/highlight/themes/`, colorent les mêmes classes et conviennent aussi : voir
[Thème](theme.md).
