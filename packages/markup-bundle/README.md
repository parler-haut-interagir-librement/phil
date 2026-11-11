# phil/markup-bundle

**Markdown, BBCode, the wikis, UJO and user HTML rendered safely in Twig, converted into UJO, with phil/markup as Symfony services.**

phil/markup-bundle brings phil/markup into a Symfony application. Its configuration names the formats texts may be
written in, the one taken when none is named, the level the filters purify at, and the highest level of each format,
so that BBCode written by members never reaches `all` whatever a template asks. It registers the UJO renderer,
`Markup`, its compiler and its purifier as services, and aliases the compiler and the purifier by their interfaces,
`MarkupCompiler` and `HtmlPurifier`, so an application can point either at its own class. `UjoConverter` gives the
reader of every enabled format and converts a source into a UJO document or its JSON, the step a text takes when it is
published or opened in a block editor. With Symfony Form installed, `MarkupFormatType` lets a writer pick a format
among the enabled ones; with Twig, the `markup_html` filter compiles and purifies a source, `markup_purify` purifies
stored HTML again, and `markup_ujo` gives the UJO JSON of a source. With `phil/typography` installed, every format gets
smart punctuation — typographic quotes, dashes, ellipses, the spaces of the locale — in the locale of the request. An
add-on bundle brings a format of its own — a dialect of Markdown, or a reader of its own — by declaring it in
`added_formats`, and every part of the bundle takes it by its name.

Use it in a Symfony application whose templates show text that visitors or editors write, or that converts what was
written in a markup language into the blocks of a block editor. Outside Symfony, or to compile, purify and read from
plain PHP code alone, phil/markup is enough. A compiler or purifier the application replaces reaches `Markup`, the
filters and phil/content-bundle alike.

The bundle holds no markup logic of its own. It wires the library's readers, compiler and purifier, and phil/ujo's
renderer, into the container, keeps each text to the formats and levels of the configuration, and exposes them to
forms and to Twig.

## Status

| Part | State |
|---|---|
| The enabled formats, the default format, the default level and the ceiling of each format: `MarkupFormats` | Implemented and tested |
| The formats of add-ons, `added_formats`: their label and their reader | Implemented and tested |
| The UJO renderer, `Markup`, `MarkupCompiler` and `HtmlPurifier` as services | Implemented and tested |
| `UjoConverter`: the readers of the enabled formats, any of them into UJO and its JSON | Implemented and tested |
| `MarkupFormatType`, a choice of an enabled format, its labels in English and French | Implemented and tested |
| The `markup_html`, `markup_purify` and `markup_ujo` filters | Implemented and tested |
| Smart punctuation in every format through `phil/typography`, in the locale of the request, can be switched off | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and registering. |
| [Configuration](doc/configuration.md) | The formats, the levels, smart punctuation, the image hosts, replacing the compiler or the purifier. |
| [Conversion into UJO](doc/conversion.md) | `UjoConverter`: the readers, the document, the JSON. |
| [Forms](doc/forms.md) | `MarkupFormatType`. |
| [Twig](doc/twig.md) | The three filters, and what a render costs. |
| [Exceptions](doc/exceptions.md) | `UnavailableFormat`, and what the library throws through the bundle. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type and service of the bundle. |

## Quick start

```yaml
phil_markup:
    formats: [markdown, bbcode, text]
    levels: { bbcode: forum }
```

```twig
{{ message.source|markup_html('bbcode', 'forum') }}
{{ article.body|markup_purify }}
<div data-blocks="{{ draft.source|markup_ujo('markdown') }}"></div>
```

```php
$json = $converter->toJson($draft->source, $draft->format);   // what the published version keeps
```

## Requirements

PHP 8.5 or later, Symfony 8.2 or later, `phil/markup` and `phil/ujo`; Symfony Form for the form type, Twig for the
filters.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
