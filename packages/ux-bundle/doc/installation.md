# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `symfony/dependency-injection`, `symfony/config`, `symfony/http-kernel` | ^8.2 | The bundle class and its configuration |
| `symfony/twig-bundle`, `twig/twig` | ^8.2, ^3.30 | The form theme and its namespace |
| `phil/ux-css` | ^0.5 | The stylesheet the bundle maps |
| `symfony/form` | ^8.2 | The forms the theme renders; Symfony registers the form themes only with it |
| `symfony/asset-mapper` | optional | The stylesheet served as `phil-ux/phil-ux.css` |

```sh
composer require phil/ux-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\UxBundle\PhilUxBundle::class => ['all' => true],
];
```

## Configuration

```yaml
# config/packages/phil_ux.yaml
phil_ux:
    form_theme: true    # false: the forms keep the themes of twig.form_themes only
```

`form_theme` is read while the bundles prepend their configuration, before environment variables are resolved: write
it as a plain boolean, not as an environment variable.

## The stylesheet

With AssetMapper, the bundle maps the `dist/` directory of phil/ux-css as `phil-ux/`. Link it in the base template:

```twig
<link rel="stylesheet" href="{{ asset('phil-ux/phil-ux.css') }}">
```

or list it in the importmap and import it from the entry script:

```php
// importmap.php
return [
    // ...
    'phil-ux/phil-ux.css' => ['path' => 'phil-ux/phil-ux.css', 'type' => 'css'],
];
```

```js
// assets/app.js
import 'phil-ux/phil-ux.css';
```

The Flex recipe of phil/ux-css writes the same mapping in `config/packages/phil_ux_css.yaml`; with the bundle
installed, that file is not needed, and keeping it changes nothing. Load the stylesheet before the application's own
styles: its rules sit in cascade layers, so the application's styles, written outside any layer, win either way. The
layers, the tokens and the themes are described in the documentation of phil/ux-css.

Without AssetMapper, the application imports `vendor/phil/ux-css/dist/phil-ux.css` through its own pipeline.

## The components

The bundle installs no component. The Twig components of PHIL UX are recipes of phil/ux-kit, which the Symfony UX
Toolkit copies into the application:

```sh
php bin/console ux:install field --kit=github.com/parler-haut-interagir-librement/ux-kit
```

## The back-office

Sites built on PHIL bundles use EasyAdmin as their back-office. EasyAdmin's pages render their forms with EasyAdmin's
own theme, listed with `only`, so the PHIL UX form theme does not reach them. `phil/ux-easyadmin-bundle` dresses the
back-office in the PHIL UX colours.
