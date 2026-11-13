# phil/citations

**Citations and bibliographies in common styles: references kept as CSL-JSON, imported from BibTeX and RIS, and
written in an author-date, a notes or a numeric style, in English or French, as text or escaped HTML.**

phil/citations keeps each cited document as a typed CSL-JSON reference — its kind among the item types of CSL 1.0.2,
its names in their parts, its dates partial or ranged, its other variables as text — and reads and writes CSL-JSON,
the exchange format of citation managers. It imports BibTeX, with its `@string` macros, its LaTeX accents and its
`crossref`, and RIS, the tagged format of reference managers and library catalogues; both readers are bounded against
hostile files and fuzzed.

A text's citations go through one processor with a style and the terms of a language. Three styles are written in
PHP: author-date in the manner of APA, notes and bibliography in the manner of Chicago, numeric in the manner of IEEE.
The processor resolves duplicates, numbers and orders the bibliography as the style asks, gives the year letters
(« 2020a ») and tells a first cite from a later one; another style plugs in through one interface.

It is a plain PHP library built on `ext-intl`, `ext-mbstring` and `ext-ctype`. It holds no CSL style file: a processor of
CSL styles the application provides would be one more style.

## Status

| Part | State |
|---|---|
| The CSL-JSON model: item types, names, partial and ranged dates, variables | Implemented and tested |
| CSL-JSON read and written, bounded in size, items and depth | Implemented and tested |
| BibTeX import: macros, concatenation, LaTeX accents and commands, names, `crossref`, BibLaTeX dates | Implemented and tested |
| RIS import: types, tags, continued lines, names, made ids | Implemented and tested |
| Duplicates by DOI, or by title, year and first author | Implemented and tested |
| Author-date, notes and numeric styles, in English and French, as text and as escaped HTML | Implemented and tested |
| Limits against hostile input, fuzz targets for the three readers | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [References and import](doc/references.md) | The model, CSL-JSON, BibTeX, RIS, the limits and the duplicates. |
| [Citing](doc/citing.md) | Citations, the processor, the three styles, the terms, text and HTML. |
| [Extension points](doc/extension-points.md) | What an application may replace or add, and when it is stable. |
| [Testing](doc/testing.md) | The package's tests, its fuzz targets and its mutation bar. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/citations
```

```php
use Phil\Citations\Citation;
use Phil\Citations\CitationProcessor;
use Phil\Citations\Cite;
use Phil\Citations\Import\BibTeXReader;
use Phil\Citations\ReferenceList;
use Phil\Citations\Style\AuthorDate;
use Phil\Citations\Terms;

$references = new ReferenceList(new BibTeXReader()->read($bibtex));
$rendered = new CitationProcessor(new AuthorDate(), Terms::english())->render($references, [
    new Citation(new Cite('knuth1984', '12')),
]);

echo $rendered->citations[0]->toHtml();     // (Knuth, 1984, p. 12)
echo $rendered->bibliography[0]->toHtml();  // Knuth, D. E. (1984). <i>The TeXbook</i>. Addison-Wesley.
```

## Requirements

PHP 8.5 or later, with `ext-intl`, `ext-mbstring` and `ext-ctype`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
