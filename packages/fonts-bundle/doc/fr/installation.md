<!-- phil-dev source-sha256=588108290748f7aabaf6c45398125d59618e15bcc57e4756e6fe3b5abd1f3cd0 -->
# Installation

## Prérequis

| Prérequis | Raison |
|---|---|
| PHP 8.5 ou plus récent | Classes en lecture seule, `array_find()`, `new` sans parenthèses |
| `phil/fonts` | La lecture des fichiers de police, les règles `@font-face` et les fiches de licence |
| `symfony/config`, `symfony/dependency-injection` 8.2 | La classe du bundle, sa configuration et ses services |
| `symfony/console` 8.2 | Les trois commandes |
| `symfony/filesystem` 8.2 | L'écriture du registre des licences et de la feuille de style |
| `ext-brotli`, facultative | La lecture des fichiers WOFF2 |
| `twig/twig`, facultatif | La fonction `fonts_css()` |
| `phil/paged-media`, facultatif | `PdfFonts` |
| `phil/media-bundle`, facultatif | `MediaFonts`, quand le bundle est aussi activé |

## Installer

```sh
composer require phil/fonts-bundle
```

Sans Symfony Flex, activez le bundle :

```php
// config/bundles.php
return [
    // …
    Phil\FontsBundle\PhilFontsBundle::class => ['all' => true],
];
```

## `ext-brotli` pour WOFF2

WOFF2 compresse ses tables avec Brotli, que PHP ne décode pas seul. Le bundle lit les fichiers WOFF2 quand l'extension
`brotli` est chargée, et la détecte de lui-même :

```sh
pecl install brotli
```

Sans elle, `phil:fonts:list` liste un fichier WOFF2 comme « Not read: ext-brotli is not loaded ». Le lire directement
lance `FontUnreadable`, avec un message qui dit d'installer l'extension ou d'utiliser le fichier WOFF, TrueType ou
OpenType de la police. Les règles `@font-face` tirent alors les métadonnées de la face de ces équivalents. Garder un
fichier `.woff` ou `.ttf` à côté de chaque fichier `.woff2` suffit pour un serveur sans l'extension.
