# phil/highlight-bundle

**Code coloured on the server in a Symfony application: in UJO documents, in templates, with a theme ready to link.**

phil/highlight-bundle puts `phil/highlight` in a Symfony application. It registers the `Highlighter` as a service,
with the languages the application writes itself — any service implementing `Language`, autoconfigured — and the
aliases of its configuration; colours the code blocks of every UJO document that `phil/ujo-bundle` renders; adds the
`highlight` Twig filter; and maps a theme stylesheet of its own for AssetMapper.

It is for any Symfony application that shows code: documentation, a blog, a forum, a wiki. A code block written in a
block editor or in Markdown comes out coloured with nothing to write, and a template colours a snippet with
`{{ snippet|highlight('php') }}`. The page needs no script; the highlight controller of `phil/ux-bundle` still
colours, in the browser, the blocks whose language the server does not know.

It is a thin bundle built on `symfony/config` and `symfony/dependency-injection`. Twig,
`phil/ujo-bundle` and AssetMapper are optional: the filter is registered only when Twig is installed, the code blocks
are coloured only when that bundle's renderer is in the container, and the stylesheet is mapped only when AssetMapper
is installed.

The design is small: `PhilHighlightBundle` reads the configuration and maps the stylesheet, `HighlightServices`
registers the services, `HighlighterPass` adds the languages and the aliases and decorates the UJO renderer,
`AliasedLanguage` gives a language its configured aliases, and `HighlightExtension` holds the filter.

## Status

| Part | State |
|---|---|
| `Highlighter` as a service: the shipped languages, the application's own by autoconfiguration, configured aliases | Implemented and tested |
| The code blocks of UJO documents coloured, through `phil/ujo-bundle`'s renderer; can be switched off | Implemented and tested |
| Twig: `highlight`, the code escaped and coloured, or escaped alone for a language it does not know | Implemented and tested |
| The theme stylesheet, light and dark, mapped as `phil-highlight/highlight.css` | Implemented |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and enabling the bundle. |
| [Configuration](doc/configuration.md) | Every option, and the services it gives. |
| [Languages](doc/languages.md) | The application's own languages, and more names for any language. |
| [UJO documents](doc/ujo.md) | The code blocks of UJO documents coloured on the server. |
| [Twig](doc/twig.md) | The `highlight` filter and the HTML around it. |
| [Theme](doc/theme.md) | The stylesheet, its colours and changing them. |
| [Extension points](doc/extension-points.md) | What an application implements, replaces or relies on. |
| [Exceptions](doc/exceptions.md) | What the bundle and the library throw. |
| [Testing](doc/testing.md) | The bundle's tests, and testing an application that uses it. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```sh
composer require phil/highlight-bundle
```

```yaml
# config/packages/phil_highlight.yaml
phil_highlight:
    aliases:
        vue: html
```

```twig
<link rel="stylesheet" href="{{ asset('phil-highlight/highlight.css') }}">

<pre><code class="language-php">{{ snippet|highlight('php') }}</code></pre>
{{ article.body|ujo_html }}  {# its code blocks coloured #}
```

## Requirements

PHP 8.5 or later, Symfony 8.2 and `phil/highlight`. Twig 3.21, for the filter; `phil/ujo-bundle`, for the code
blocks of UJO documents; `symfony/asset-mapper`, for the stylesheet.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
