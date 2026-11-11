# Testing

| Area | What is covered |
|---|---|
| `Numbering` | Every style, the limits of roman numerals and of rounds of symbols, refusals |
| `NoteOptions`, `NoteLabels` | The ranges of options, the search by locale and language, the two languages |
| `NoteResolver` | Reading order, nested blocks and list items, literal blocks, every restart, every problem, identifiers of digits |
| `Html\NotesRenderer` | The exact HTML of each placement, backlinks, sections, sanitizing, links in links, white space, prefixes, escaping |
| `Text\NotesPlainText` | References marked, notes listed, literal blocks, the caller's conversions |
| `Stylesheet` | The rules of GCPM, the margin column, the toggle, the animation behind `prefers-reduced-motion` |

Infection holds the library at 100 % of covered mutants killed.

```sh
vendor/bin/phpunit packages/footnotes-sidenotes/tests
```

## Benchmarks

`NoteResolverBench` measures resolving, rendering at the end of the document, rendering at the foot of the page and the
plain text of books of 100, 1,000 and 5,000 notes. From the monorepo root:

```sh
composer bench -- packages/footnotes-sidenotes/tests/Benchmark
```

## Fuzzing

The target `footnotes-sidenotes` decodes, resolves and renders any input in every placement, from the seeds of
`tests/Fuzz/corpus`.

```sh
composer fuzz -- footnotes-sidenotes
```
