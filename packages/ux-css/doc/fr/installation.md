<!-- phil-dev source-sha256=d99366683dcdcf8a99f3c6397c17a0378f81dfc9e0ea64d4eb316159ea8bf01c -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| Un navigateur de 2024 ou plus récent | Les couches en cascade, `light-dark()`, `oklch()`, `dvh`, `text-wrap` |
| PHP 8.5 ou plus récent, pour la règle de contraste seulement | Les classes en lecture seule, les constantes typées, `array_all()` |

## Avec AssetMapper

```sh
composer require phil/ux-css
```

Le paquet est une librairie, pas un bundle : l'application associe son dossier `dist/`, une fois. Sa recette Symfony
Flex le fait dans un fichier à elle, `config/packages/phil_ux_css.yaml`, qu'AssetMapper fusionne avec les chemins que
l'application liste déjà. La recette vit dans le dépôt de recettes de PHIL, sous `phil/ux-css/<version>/`, lue une fois
que l'application liste ce dépôt dans `extra.symfony.endpoint`, comme le dit son README. Sans elle, écrivez
l'association à la main :

```yaml
# config/packages/asset_mapper.yaml
framework:
    asset_mapper:
        paths:
            - assets/
            vendor/phil/ux-css/dist: phil-ux
```

```twig
<link rel="stylesheet" href="{{ asset('phil-ux/phil-ux.css') }}">
```

Ou depuis npm, par l'importmap :

```sh
php bin/console importmap:require @phil/ux-css/dist/phil-ux.css
```

## Avec npm

```sh
npm install @phil/ux-css
```

```js
import '@phil/ux-css';
```

## Un fichier copié

Sans Composer ni npm, copiez `dist/phil-ux.css` dans l'application, par exemple en `assets/styles/phil-ux.css`, et
mettez-le à jour comme une dépendance.

## Les sources ou le fichier unique

`dist/phil-ux.css` est le fichier unique : les sept couches intégrées. `css/phil-ux.css` est l'entrée des sources, qui
importe chaque fichier dans sa couche avec `@import url(…) layer(…)` ; une chaîne qui suit les `@import` peut la
prendre, et un site qui ne veut que certaines couches importe les fichiers dont il a besoin dans les mêmes couches.

## Les couches en cascade

```css
@layer phil.palette, phil.colors, phil.dimensions, phil.base, phil.layout, phil.components, phil.utilities;
```

Chaque style de phil-ux vit dans l'une de ces couches. Les styles d'un site, écrits hors de toute couche, l'emportent
sur toutes ; un site qui emploie ses propres couches les déclare après `phil.utilities`.
