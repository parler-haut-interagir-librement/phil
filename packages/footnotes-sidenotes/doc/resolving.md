# Resolving notes

`NoteResolver::resolve()` takes a document and gives a `Resolution`. The document should be normalized first, as
`Ujo::decode()` and `Ujo::normalize()` do: normalization gives references their canonical form, the only one read.

## What is collected

A **note** is a `footnote` block, `{id, text}`, wherever it stands: before its references, after them, nested in a
column. A **reference** is `<sup data-footnote="id">…</sup>` in any text of any block, nested blocks and list items
included. The content of the element is replaced at output, so a number stored there by a reader of Markdown counts for
nothing. The text of `code`, `mermaid` and `math` blocks is not HTML: what looks like a reference there is left alone.

## Reading order

The blocks are walked in document order, the values of a block in the order of its data, each nested block where it
stands. A note takes its number at its first reference. A note referenced several times is one note, with one number
and several `NoteReference` values.

## Restarts

`NoteOptions::$restart` says where the numbering starts again:

| `Restart` | The numbering starts again |
|---|---|
| `Document` | Never: one numbering for the document |
| `Chapter` | At each `header` block whose level is `chapterLevel` or above; 1 by default |
| `PageMarker` | At each block of a type in `pageMarkers`; `['pageBreak']` by default |

A chapter or a page marker counts wherever it stands, nested or not. `Note::$scope` gives the run a note belongs to. A
note referenced again in a later run keeps the number of its first reference.

## Numbering

| Factory | Writes |
|---|---|
| `Numbering::decimal()` | `1`, `2`, `3` |
| `Numbering::lowerRoman()` | `i`, `ii`, `iii`, decimal past 3999 |
| `Numbering::upperRoman()` | `I`, `II`, `III`, decimal past 3999 |
| `Numbering::symbols()` | `*`, `†`, `‡`, `§`, `‖`, `¶`, then `**`, `††`, then `***`, decimal past three rounds |

`Numbering::symbols(['a', 'b'])` takes the caller's own sequence. `NoteOptions::$numbering` applies by default;
`$locales` gives the numbering of a locale or of a language, and `resolve($document, 'fr-CA')` looks for `fr-CA`, then
`fr`, then the default.

## Problems

`Resolution::$problems` lists what was found, each a `Problem` with its `kind`, its `noteId`, the `blockId` where it
stands and `occurrences`:

| `ProblemKind` | Meaning | At output | `isError()` |
|---|---|---|---|
| `MissingNote` | A reference names no note | The reference is left out | Yes |
| `UnreferencedNote` | A note no reference names | The note is left out | Yes |
| `DuplicateId` | A second note of an identifier | The first note is kept | Yes |
| `ReferenceInNote` | The text of a note holds a reference | The reference is left out | Yes |
| `RepeatedReference` | A note is referenced several times | One note, one backlink per reference | No |

`Resolution::isClean()` says that no problem loses anything; `problemsOf($kind)` reads one kind. A publishing flow can
refuse a document that is not clean, a reader's page can show it anyway.

## Cost

The resolver is one walk: the time grows with the size of the document, and a text without a reference costs one search
for a substring. See [Testing](testing.md#benchmarks).
