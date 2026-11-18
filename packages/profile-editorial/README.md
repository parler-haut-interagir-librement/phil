# phil/profile-editorial

[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/parler-haut-interagir-librement/profile-editorial/badge)](https://scorecard.dev/viewer/?uri=github.com/parler-haut-interagir-librement/profile-editorial)

**One `composer require` for the PHIL packages that publish editorial content in a Symfony application: text written
in several markups and shown as safe HTML, citations and bibliographies, footnotes and sidenotes, content warnings
readers control, and the schema.org data search engines read.**

phil/profile-editorial is a Composer metapackage: it holds no code. Requiring it installs five PHIL packages that are
often used together by sites that publish articles, books, games or event pages. Each member stays an independent
package, with its own configuration, documentation and releases: the profile couples no service to another, it only
saves looking for them one by one and picking constraints that work together.

It is for Symfony applications whose authors write long-form content and whose readers expect it to be readable,
sourced and honest about what it contains. An application that needs only one of these parts requires that member
alone.

## What it installs

| Member | Constraint | Brings | Why it belongs here |
|---|---|---|---|
| `phil/markup-bundle` | `^0.1` | `phil/markup` 0.2, `phil/ujo`, `phil/svg-sanitizer`, `phil/css-sanitizer` | Authors write Markdown, BBCode, wiki syntaxes, UJO (Universal JSON Output) or HTML; readers get purified HTML, at the level the application chooses. |
| `phil/citations` | `^0.1` | nothing more | References kept as CSL-JSON, imported from BibTeX and RIS, and written in author-date, notes or numeric styles, in English or French. |
| `phil/footnotes-sidenotes` | `^0.1` | `phil/ujo` | Footnotes and margin notes of UJO documents, numbered and checked, shown as accessible endnotes, sidenotes or at the foot of printed pages. |
| `phil/content-warnings-bundle` | `^0.1` | `phil/content-warnings` | Warnings from a translated catalogue of themes on any content, folded or hidden as each reader chooses. |
| `phil/structured-data-bundle` | `^0.1` | `phil/structured-data`, `phil/indexing-consent`, `phil/url` | One schema.org graph per page, printed safely as JSON-LD, and left out of pages withdrawn from search engines. |

The [members](doc/members.md) page gives each one's role, what it adds to the application and what it needs.

## What each member brings to the application

No member ships a Symfony Flex recipe: Flex registers each bundle in `config/bundles.php` and copies no file. Each
member then works with its defaults, and its own documentation says what to configure.

| Member | Bundle registered | Files it provides |
|---|---|---|
| `phil/markup-bundle` | `Phil\MarkupBundle\PhilMarkupBundle` | Translations of its labels |
| `phil/content-warnings-bundle` | `Phil\ContentWarningsBundle\PhilContentWarningsBundle` | The template `@PhilContentWarnings/fold.html.twig`, the Stimulus controller `reveal` in `assets/package.json`, translations in six languages |
| `phil/structured-data-bundle` | `Phil\StructuredDataBundle\PhilStructuredDataBundle` | The command `phil:structured-data:check` |
| `phil/citations` | none: a library | none |
| `phil/footnotes-sidenotes` | none: a library | none |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | The stability a PHIL alpha needs, registering the bundles without Flex, what to configure next. |
| [Members](doc/members.md) | Each member: its role, what it adds, what it needs, its documentation. |
| [Upgrading](doc/upgrading.md) | How the constraints move, and how to leave out one member. |
| [Extension points](doc/extension-points.md) | Where an application changes the behaviour: in the members. |

## Quick start

```sh
composer config minimum-stability alpha
composer config prefer-stable true
composer require phil/profile-editorial
```

To leave out one member — here `phil/content-warnings-bundle` —, require the others directly instead of the profile:

```sh
composer remove phil/profile-editorial
composer require phil/markup-bundle:^0.1 phil/citations:^0.1 phil/footnotes-sidenotes:^0.1 phil/structured-data-bundle:^0.1
```

## Requirements

PHP 8.5 or later and Symfony 8.2 or later, with the extensions the members need: `ext-intl`, `ext-mbstring`,
`ext-dom`, `ext-libxml`, `ext-ctype` and `ext-filter`. The members are alpha releases: the project accepts them with
`minimum-stability: alpha` and `prefer-stable: true`. Twig is needed for the filters, functions and templates of the
bundles.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
