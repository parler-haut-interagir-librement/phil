# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the library declares and is tested on |
| `ext-hash` | any | The default prefix of identifiers |
| `ext-mbstring` | any | Symbols measured in characters |
| `phil/ujo` | 0.1 | The documents, their sanitizer and their renderer |

```sh
composer require phil/footnotes-sidenotes
```

The library declares no service and reads no configuration. In a Symfony application, register `NoteResolver` and
`NotesRenderer` as services if you wish: they keep nothing between calls, so one instance serves every request of a
worker.

## The stylesheets

The package ships its sheets in `resources/css`; `Stylesheet::Screen->path()` and `Stylesheet::Print->path()` give
their absolute paths, to copy or to map with AssetMapper, and `contents()` their text, to inline. Neither needs the
other, nor phil-ux: each value reads a `--phil-*` token and falls back to a plain one. `Stylesheet::PrintPerPage` comes
after the print sheet ([Print](print.md)).

## Optional packages

| Package | For |
|---|---|
| `phil/typography` | The typography of a locale on the text of notes, through the caller's `HtmlRenderer` filters |
| `phil/paged-media` | The print output turned into a PDF |
