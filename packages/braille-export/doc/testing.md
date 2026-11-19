# Testing

| Area | What is covered |
|---|---|
| `UncontractedTranslator` | Every letter, accented letter, capital, word in capitals, number, mark within and after a number and punctuation of each code; letters folded to their base; characters left out or refused in strict mode; the language tags read; the contractions and languages refused |
| `ChainTranslator` | The first translator that supports the language, the fallback, none |
| `BrailleExporter` | The indentation of each block, line breaks, white space folded, words cut with and without their own hyphen and never after an announcing cell, headings of each level kept with their next line, nested and ordered lists, linearized tables in English and French, page numbers and their room, print pages and their lines, page breaks, sections, volumes, the page limit, one batch per document, the answer of a translator checked |
| Writers | Every one of the 64 cells in BRF, eight-dot cells refused, PEF and its metadata checked against XML, its identifier derived from the content, the Unicode preview |
| `UjoReader` | Each UJO block read, missing or wrong data read as defaults, another plain text |
| `PageFormat`, the blocks, `Braille` | Their bounds |

The unit tests, under `tests/Unit`, need no program, no kernel and no network:

```sh
vendor/bin/phpunit packages/braille-export/tests/Unit
```

In the monorepo, Infection holds the package at 100 % of covered mutants.

## Fuzzing

The layout is fuzzed from the monorepo root with any text, in French and in English, laid out as each kind of block on
a page of 12 cells by 4 lines; a row that is not braille, a row too wide, a page too long or a BRF file that cannot be
written stops the run:

```sh
composer fuzz -- layout
```

## Benchmark

`tests/Benchmark/ExportBench.php` lays out a chapter of 200 French paragraphs, about 90 braille pages, and writes it
as BRF and as PEF:

```sh
composer bench -- --filter=ExportBench
```
