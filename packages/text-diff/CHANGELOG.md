# Changelog

All notable changes to `phil/text-diff` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- `Operation` and `OperationKind`, the kept, deleted and inserted runs of a difference.
- `Diff`: the operations, both texts given back, `isIdentical()`, and `changes()`, the list of `Change`s —
  `ChangeKind` insertion, deletion or replacement — with their offsets in code points.
- `Tokenizer`: texts cut into words, lines, paragraphs, or HTML words with tags, comments and character references
  whole, a tag read by the grammar of its attributes so that a `>` in a quoted value stays inside it; tokens that give
  the text back byte for byte; in words and HTML, each character of the Han, Hiragana, Katakana, Thai, Lao, Khmer
  and Myanmar scripts a token alone, with its marks, so that texts written without spaces are compared character by
  character.
- Myers' O(ND) difference algorithm over tokens, written from scratch: the common start and end set aside, the search
  bounded by a number of edits past which the middle is replaced whole, white space between two changes joined to
  them, deletions before insertions in each change.
- `TextDiff`, the library in one object: `compare()`, `words()`, `lines()`, `paragraphs()`, `html()`, `documents()`,
  `toHtml()` and `documentToHtml()`; its `Normalizer`, given or default, for the blocks of a document difference.
- `HtmlFormatter`: a difference as HTML with `<ins>` and `<del>`, plain text escaped, HTML keeping the structure of
  the new text, its tags and character references as they are and the text between them escaped.
- UJO documents compared block by block: blocks paired by identifier at every level of nesting, `BlockChange` with
  its `BlockChangeKind` — unchanged, changed, added, removed, moved —, its parent and positions, and the
  `FieldChange`s of its type, data and tunes by path, texts compared word by word; `DocumentDiff` with its changes and
  top level; the HTML of a document difference, each block normalized, then rendered by `phil/ujo` in a `<div>` named
  after what happened to it.
- `TextDiffException`, implemented by `InvalidText` and `InvalidConfiguration`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
