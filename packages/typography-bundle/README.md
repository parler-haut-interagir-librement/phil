# phil/typography-bundle

**Typographic quotes, dashes, French spaces and soft hyphens everywhere a Symfony application prints text.**

phil/typography-bundle puts `phil/typography` in a Symfony application. It builds one typographer per locale from the
configuration — the preset of the locale, or the fixers the application lists —, registers each configured locale as
a service of its own, adds the `typography` and `typography_text` Twig filters, and, when `phil/ujo-bundle` is
installed, runs the typographer on the HTML of every UJO document the application renders — each in the locale of the
request, unless a template names another.

It is for any Symfony application that shows text written by people: a CMS, a blog, a forum, a shop. A template
writes `{{ article.body|typography }}`; the UJO documents rendered by `ujo_html` or `Ujo::toHtml()` come out with
their typography fixed and nothing to write.

It is a thin bundle built on `symfony/config` and `symfony/dependency-injection`. Twig and
`phil/ujo-bundle` are optional: the filters are registered only when Twig is installed, and the UJO integration only
when that bundle's renderer is in the container.

The design is small: `PhilTypographyBundle` reads the configuration, `TypographyServices` registers the services,
`TypographyExtension` holds the filters, `TypographyHtmlFilter` and `UjoFilterPass` decorate the UJO renderer.

## Status

| Part | State |
|---|---|
| Configuration: default locale, hyphenation and its experimental languages, protected elements, fixers by locale | Implemented and tested |
| `Typographers`, `Typographer` and one service per configured locale, injectable by argument name | Implemented and tested |
| Twig: `typography` for HTML, escaping what is not marked safe; `typography_text` for plain text | Implemented and tested |
| The locale of the request, through Symfony's `LocaleSwitcher`, for the filters and the UJO pass | Implemented and tested |
| Typography on the HTML of UJO documents, through `phil/ujo-bundle`'s renderer | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and enabling the bundle. |
| [Configuration](doc/configuration.md) | Every option, and the services it gives. |
| [Twig](doc/twig.md) | The two filters, escaping, and the locale of a template. |
| [UJO documents](doc/ujo.md) | The typographic pass over the HTML of UJO documents. |
| [Exceptions](doc/exceptions.md) | What the bundle and the library throw. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | The bundle's tests, and testing an application that uses it. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```sh
composer require phil/typography-bundle
```

```yaml
# config/packages/phil_typography.yaml
phil_typography:
    default_locale: fr_CA
    locales:
        en_GB: []            # a service with the preset of en_GB
```

```twig
<h1>{{ article.title|typography_text }}</h1>
{{ article.bodyHtml|raw|typography }}
<aside lang="en-GB">{{ article.quote|typography('en_GB') }}</aside>
```

## Requirements

PHP 8.5 or later, Symfony 8.2 and `phil/typography`. Twig 3.21, for the filters; `symfony/translation`, for the
locale of the request; `phil/ujo-bundle`, for the UJO integration.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
