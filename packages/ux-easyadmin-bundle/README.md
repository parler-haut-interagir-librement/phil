# phil/ux-easyadmin-bundle

**The EasyAdmin back-office in the colours of PHIL UX, light and dark, without restyling EasyAdmin.**

Sites built on PHIL bundles use EasyAdmin as their back-office. phil/ux-easyadmin-bundle gives that back-office the
look of PHIL UX: EasyAdmin's own variables take the colour roles, corners, focus width and fonts of phil/ux-css, so
the accent, the surfaces, the sidebar, the tables, the forms, the alerts and the badges follow the site's design
system, in the light scheme, the dark scheme and the automatic one.

It changes values, not markup. The theme is one stylesheet in EasyAdmin's cascade layers: the tokens of phil/ux-css
sit under EasyAdmin's `vendor` layer, where they lose to every style of the back-office, and the theme is a layer
declared after EasyAdmin's, where it wins over EasyAdmin's tokens and loses to the application's own styles. The
element rules of phil/ux-css, on `button`, `input` or `code`, are left out, so the back-office keeps EasyAdmin's
components. No EasyAdmin template is overridden.

The bundle links the stylesheet on every page of every dashboard, after the dashboard's own stylesheets; one key
leaves it to each dashboard instead.

## Status

| Part | State |
|---|---|
| The theme: EasyAdmin's variables on the tokens of phil/ux-css, in EasyAdmin's cascade layers, light and dark | Implemented and tested |
| The theme checked against EasyAdmin 5.10: every variable it sets declared by EasyAdmin, its colours computed by a real browser | Implemented and tested |
| The stylesheet mapped as `phil-ux-easyadmin/` in AssetMapper | Implemented and tested |
| The stylesheet linked on every page of every dashboard, and `theme: false` | Implemented and tested |
| The Flex recipe: the bundle registered, its configuration written | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, why the sites of PHIL bundles have EasyAdmin, registering, the recipe, the configuration, `configureAssets()`. |
| [The theme](doc/theme.md) | The layers, the variables, dark mode, what the theme leaves to EasyAdmin, changing it. |
| [Extension points](doc/extension-points.md) | What an application configures, overrides or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type and file of the bundle. |

## Quick start

```sh
composer require phil/ux-easyadmin-bundle
```

Every dashboard of the application now renders in the colours of PHIL UX. A dashboard changes a value in its own
stylesheet, written outside any layer:

```css
/* assets/styles/admin.css, listed by the dashboard's configureAssets() */
:root, .ea-dark-scheme {
    --phil-color-brand-fill-loud: light-dark(oklch(0.45 0.15 150), oklch(0.75 0.12 150));
}
```

## Requirements

PHP 8.5 or later; EasyAdmin 5.10; Symfony 8.2's AssetMapper, configuration, dependency injection and HTTP kernel;
phil/ux-css 0.5.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
