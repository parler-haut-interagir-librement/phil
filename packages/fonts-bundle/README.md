# phil/fonts-bundle

**The font files of a Symfony application, read once: their `@font-face` rules, their licences checked by a command,
and the same fonts handed to the PDF writer.**

phil/fonts-bundle puts `phil/fonts` in a Symfony application. It reads a directory of font files — TrueType,
OpenType, collections, WOFF and, when `ext-brotli` is loaded, WOFF2 — into a catalogue of faces with their family,
weight, style, embedding permissions and licence. From it the bundle writes the `@font-face` rules of the directory,
one rule per face with each of its files listed smallest first, for a stylesheet or a template; keeps a licence register
beside the fonts and lists, in a command that fails a build, every file added, removed or changed since its licence was
last checked; and hands the TrueType faces to the native PDF writer of `phil/paged-media` and reads the fonts uploaded to
`phil/media-bundle`.

Without `ext-brotli`, WOFF2 files cannot be read: they stay in the catalogue with that reason, the commands say so
plainly, and their WOFF, TrueType or OpenType siblings give the metadata — the WOFF2 file is still served first in the
`@font-face` rule, since the browser reads it.

It is for any Symfony application that serves fonts it did not draw: a site that self-hosts its typefaces, a print or
e-book pipeline that embeds them, a build that must not ship a font nobody checked the licence of.

It is a bundle built on `symfony/config`, `symfony/dependency-injection`, `symfony/console` and
`symfony/filesystem`. Twig, `phil/paged-media` and `phil/media-bundle` are optional: each integration is registered when
its package is installed.

The design is small: `FontFiles` reads a file with the Brotli decompressor PHP has, `FontCatalogue` holds the files of
the directory, `FontFaces` writes their rules, `Licences` compares them with the register, three commands and a Twig
function expose them, and `PdfFonts` and `MediaFonts` connect the other packages.

## Status

| Part | State |
|---|---|
| Configuration: directory, public path, `font-display`, licence register | Implemented and tested |
| `FontFiles`: WOFF2 read when `ext-brotli` is loaded, a clear message otherwise | Implemented and tested |
| `FontCatalogue`: files by path, faces by family, weight and style, unreadable files with their reason | Implemented and tested |
| `FontFaces`: one rule per face, its files smallest first, URLs under the public path | Implemented and tested |
| `Licences` and `phil:fonts:licences`: the register kept as JSON, files to check, `--write` | Implemented and tested |
| `phil:fonts:list`, `phil:fonts:css` and `fonts_css()` | Implemented and tested |
| `PdfFonts` for `phil/paged-media`, `MediaFonts` for `phil/media-bundle` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and enabling the bundle, and `ext-brotli`. |
| [Configuration](doc/configuration.md) | Every option, and the services it gives. |
| [The catalogue](doc/catalogue.md) | `FontFiles`, `FontCatalogue` and `CatalogueEntry`: reading, WOFF2, finding a face. |
| [`@font-face` rules](doc/font-face.md) | `FontFaces`, `phil:fonts:css` and `fonts_css()`. |
| [Licences](doc/licences.md) | `Licences` and `phil:fonts:licences`: the register, the files to check, a build that stops. |
| [Commands](doc/commands.md) | The three commands, their options and exit codes. |
| [Integrations](doc/integrations.md) | `PdfFonts` for the native PDF writer, `MediaFonts` for the media library. |
| [Extension points](doc/extension-points.md) | The services an application relies on, replaces or decorates. |
| [Exceptions](doc/exceptions.md) | What the bundle throws, beside the library's exceptions. |
| [Testing](doc/testing.md) | The bundle's tests, and testing an application that uses it. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```sh
composer require phil/fonts-bundle
```

```yaml
# config/packages/phil_fonts.yaml
phil_fonts:
    directory: '%kernel.project_dir%/public/fonts'
    public_path: /fonts
```

```twig
<style>{{ fonts_css() }}</style>
```

```sh
bin/console phil:fonts:list
bin/console phil:fonts:licences          # fails while a file's licence is unchecked
bin/console phil:fonts:licences --write  # once checked
```

## Requirements

PHP 8.5 or later, Symfony 8.2 and `phil/fonts`; `ext-brotli` to read WOFF2 files; Twig for `fonts_css()`;
`phil/paged-media` for `PdfFonts`; `phil/media-bundle` for `MediaFonts`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
