# Installation

## Requirements

| Requirement | Why |
|---|---|
| A browser of 2024 or later | Cascade layers, `light-dark()`, `oklch()`, `dvh`, `text-wrap` |
| PHP 8.5 or later, for the contrast rule only | Readonly classes, typed constants, `array_all()` |

## With AssetMapper

```sh
composer require phil/ux-css
```

The package is a library, not a bundle: the application maps its `dist/` directory, once. Its Symfony Flex recipe does
it with a file of its own, `config/packages/phil_ux_css.yaml`, which AssetMapper merges with the paths the application
already lists. The recipe lives in the recipes repository of PHIL, under `phil/ux-css/<version>/`, read once the
application lists that repository in `extra.symfony.endpoint`, as its README says. Without it, write the mapping by
hand:

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

Or from npm, through the importmap:

```sh
php bin/console importmap:require @phil/ux-css/dist/phil-ux.css
```

## With npm

```sh
npm install @phil/ux-css
```

```js
import '@phil/ux-css';
```

## A copied file

Without Composer nor npm, copy `dist/phil-ux.css` into the application, for instance as `assets/styles/phil-ux.css`,
and update it as a dependency.

## The sources or the single file

`dist/phil-ux.css` is the single file: the seven layers inlined. `css/phil-ux.css` is the entry of the sources, which
imports each file into its layer with `@import url(…) layer(…)`; a pipeline that follows `@import` can take it, and a
site that wants only some layers imports the files it needs into the same layers.

## The cascade layers

```css
@layer phil.palette, phil.colors, phil.dimensions, phil.base, phil.layout, phil.components, phil.utilities;
```

Every style of phil-ux lives in one of these layers. A site's styles, written outside any layer, win over them all;
a site that uses layers of its own declares them after `phil.utilities`.
