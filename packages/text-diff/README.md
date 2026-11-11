# phil/text-diff

**What changed between two texts, word by word, paragraph by paragraph, or block by block — shown in HTML or listed.**

phil/text-diff compares two texts and gives the shortest set of deletions and insertions that turns the old one into the
new one. It cuts texts into words, lines or paragraphs; it reads HTML without ever cutting a tag, so that the difference
of two pages is itself a well-formed page; and it compares two documents in UJO (Universal JSON Output) — the block
document format read by `phil/ujo` — block by block, by block identifier, then field by field inside each block that
changed. Each difference comes out as HTML with `<ins>` and `<del>`, or as a structured list of changes with their
offsets.

It is for any PHP application that keeps versions of what people write and must show what moved between two of them:
the revision history of an article, a page or a wiki, the review of a proposed edit, the segments of a translation
whose source changed, the audit of a document before it is published again. `phil/content-bundle` keeps the versions
of its contents; this library is how an application shows their differences.

It is a plain PHP library whose only requirements are `ext-mbstring` and `phil/ujo`. The
comparison is Eugene W. Myers' O(ND) difference algorithm, run after the common start and end of both texts are set
aside, and bounded: past a number of edits — a thousand by default — the rest is shown as replaced whole, which keeps
time and memory in check on texts rewritten from end to end. The output is deterministic.

The design is small: a `Tokenizer` cuts a text into tokens that give it back byte for byte; `Myers` finds the script;
a `Diff` holds its `Operation`s and lists its `Change`s; `HtmlFormatter` writes the HTML. For documents,
`DocumentDiffer` pairs the blocks of each level by identifier into `BlockChange`s with their `FieldChange`s, gathered
in a `DocumentDiff` that `DocumentFormatter` renders through `phil/ujo`. `TextDiff` puts them together.

## Status

| Part | State |
|---|---|
| Myers' algorithm with common start and end set aside and a bound of edits | Implemented and tested |
| Words, lines and paragraphs; HTML with tags, comments and character references whole | Implemented and tested |
| Scripts written without spaces (Han, kana, Thai, Lao, Khmer, Myanmar) compared character by character | Implemented and tested |
| White space between two changes joined to them; deletions before insertions in each change | Implemented and tested |
| HTML of a difference: plain text escaped, HTML keeping the structure of the new text | Implemented and tested |
| Change list: insertions, deletions and replacements with their offsets in code points | Implemented and tested |
| UJO documents: blocks paired by identifier at every level, kept, changed, moved, added or removed; values compared by path | Implemented and tested |
| HTML of a document difference, each block rendered by `phil/ujo` and marked | Implemented and tested |
| Lists of values other than blocks aligned by content rather than by position | Not started: they are compared by index |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Comparing texts](doc/texts.md) | `TextDiff`, the tokenizers, operations, changes and the bound of edits. |
| [HTML output](doc/html.md) | `HtmlFormatter`: plain text and HTML, what is marked and what is kept, styling. |
| [Comparing documents](doc/documents.md) | UJO documents block by block: pairing, moves, field paths, nested blocks, HTML. |
| [Versions of contents](doc/versions.md) | Showing the differences between two versions kept by `phil/content-bundle`. |
| [Exceptions](doc/exceptions.md) | `TextDiffException` and its two exceptions. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that shows differences. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/text-diff
```

```php
use Phil\TextDiff\TextDiff;

$differ = new TextDiff();

echo $differ->toHtml($differ->words('The quick brown fox', 'The slow brown dog'));
// The <del>quick</del><ins>slow</ins> brown <del>fox</del><ins>dog</ins>

echo $differ->toHtml($differ->html('<p>Hello <b>big</b> world</p>', '<p>Hello <i>small</i> world!</p>'));
// <p>Hello <del>big</del><i><ins>small</ins></i> world<ins>!</ins></p>

foreach ($differ->paragraphs($before, $after)->changes() as $change) {
    printf("%s at %d: %s -> %s\n", $change->kind->value, $change->oldOffset, $change->old, $change->new);
}
```

```php
use Phil\Ujo\Ujo;

$ujo = new Ujo();
$diff = $differ->documents($ujo->decode($oldJson), $ujo->decode($newJson));

echo $differ->documentToHtml($diff);
```

## Requirements

PHP 8.5 or later, `ext-mbstring` and `phil/ujo`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
