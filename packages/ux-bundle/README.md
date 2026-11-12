# phil/ux-bundle

**PHIL UX in a Symfony application: every form in the PHIL UX look, and the stylesheet served by AssetMapper.**

phil/ux-bundle connects PHIL UX to Symfony. It does two things:

- **The PHIL UX form theme.** Every form renders its rows as the fields of the PHIL UX kit: a row is an `isField`,
  expanded choices and sub-forms are an `isFieldSet` named by a legend, the help and the errors are tied to the
  control. The bundle adds the theme to `twig.form_themes` the way Symfony adds its own default theme, so nothing is
  written in a template; the themes the application lists come after it and win. One key turns it off.
- **The stylesheet of phil/ux-css.** With AssetMapper, the bundle maps the `dist/` directory of phil/ux-css as
  `phil-ux/`, so `asset('phil-ux/phil-ux.css')` serves the framework and the importmap can list it.

The look itself lives in the public packages of PHIL UX: phil/ux-css, the stylesheet, and phil/ux-kit, the kit of Twig
components that the Symfony UX Toolkit installs with `ux:install`. The bundle installs no component and copies no
file.

Sites built on PHIL bundles use EasyAdmin as their back-office. EasyAdmin renders its own forms with its own theme,
so the PHIL UX form theme does not change the back-office; `phil/ux-easyadmin-bundle` gives the back-office the PHIL
UX colours.

## Status

| Part | State |
|---|---|
| The PHIL UX form theme, prepended to `twig.form_themes`, and `form_theme: false` | Implemented and tested |
| The stylesheet of phil/ux-css mapped as `phil-ux/` in AssetMapper | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering, the configuration, the stylesheet. |
| [The form theme](doc/form-theme.md) | The markup of each row, the order of the themes, turning it off. |
| [Extension points](doc/extension-points.md) | What an application configures, overrides or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type and file of the bundle. |

## Quick start

```sh
composer require phil/ux-bundle
```

```twig
<link rel="stylesheet" href="{{ asset('phil-ux/phil-ux.css') }}">

{{ form(form) }}   {# rendered with the PHIL UX form theme #}
```

## Requirements

PHP 8.5 or later; Symfony 8.2's dependency injection, configuration, form, HTTP kernel and Twig bundle; Twig 3.30;
phil/ux-css 0.5. AssetMapper is optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
