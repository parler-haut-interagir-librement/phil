# phil/braille-export

**A document laid out in braille pages and written as BRF, PEF or Unicode braille, in contracted or uncontracted braille.**

phil/braille-export is a plain PHP library. It takes a simple structured document — headings, paragraphs, lists,
tables, page breaks and print page markers — or a UJO (Universal JSON Output) document, translates its text into
braille cells, lays the cells out in braille pages, and writes the pages in the format an embosser, a braille
notetaker or a screen reads.

- **Translation** goes through the `Translator` port, one batch per document. `UncontractedTranslator` writes
  uncontracted French (Code braille français uniformisé) and English (Unified English Braille) in PHP, with no program
  to install. Contracted braille, and every other language, comes from a translator with full tables, such as
  `phil/braille-export-liblouis`; `ChainTranslator` puts the PHP translator behind it for when it is missing.
- **Layout** follows the braille page: 40 cells by 25 lines by default, paragraphs starting in cell 3, headings centred
  or in cell 5 and 7 and kept with the line after them, lists indented by level, tables linearized row by row, the
  braille page number at the end of the last line, the print page at the end of the first line and a line of dots
  3-6 where a print page starts. A word too long for a line is cut after its own hyphen when it has one, so that no
  second hyphen is added.
- **Formats**: BRF, the North American ASCII braille file embossers print; PEF 2008-1, the XML of the DAISY
  Consortium that keeps volumes, sections and pages; Unicode braille for a preview on a screen.

It is a package of its own, apart from the paged-media templates: a braille edition is laid out in cells, not in
points, and needs none of a print engine.

## Status

| Part | State |
|---|---|
| The document model, and UJO documents read into it | Implemented and tested |
| `UncontractedTranslator`: uncontracted French and English | Implemented and tested |
| `ChainTranslator` and the `Translator` port | Implemented and tested |
| `BrailleExporter` and `PageFormat`: the layout of braille pages | Implemented, tested and fuzzed |
| `BrfWriter`, `PefWriter`, `UnicodeWriter` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, and the translator to choose. |
| [Documents](doc/documents.md) | The blocks of a document, and a UJO document read into them. |
| [Translation](doc/translation.md) | The `Translator` port, uncontracted French and English, contracted braille, the chain. |
| [Layout](doc/layout.md) | The page format and how each block is laid out: indentation, headings, lists, tables, page numbers, word cutting. |
| [Output formats](doc/output-formats.md) | BRF, PEF and Unicode braille. |
| [Exceptions](doc/exceptions.md) | What the package throws, and when. |
| [Security](doc/security.md) | What any text can and cannot do, and the bounds of an export. |
| [Extension points](doc/extension-points.md) | What an application may replace or add, and when it is stable. |
| [Testing](doc/testing.md) | The tests, the fuzz target and the benchmark. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/braille-export
```

```php
use Phil\BrailleExport\BrailleExporter;
use Phil\BrailleExport\Document\Document;
use Phil\BrailleExport\Document\Heading;
use Phil\BrailleExport\Document\Paragraph;
use Phil\BrailleExport\Output\BrfWriter;
use Phil\BrailleExport\Output\PefWriter;
use Phil\BrailleExport\Translation\UncontractedTranslator;

$document = new Document([
    new Heading('Chapitre premier'),
    new Paragraph("Lorsque j'avais six ans, j'ai vu une magnifique image."),
], 'fr', 'Le livre');

$book = new BrailleExporter(new UncontractedTranslator())->layout($document);

file_put_contents('book.brf', new BrfWriter()->write($book));
file_put_contents('book.pef', new PefWriter()->write($book));
```

## Requirements

PHP 8.5 or later with `mbstring`; `phil/ujo` 0.1. Contracted braille needs a translator with full tables, such as
`phil/braille-export-liblouis`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
