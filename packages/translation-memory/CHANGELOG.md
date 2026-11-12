# Changelog

All notable changes to `phil/translation-memory` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-12

### Added

- `LanguageTag`: BCP 47 tags read and written in their canonical case, read from a locale such as `fr_CA`, and their
  parent, without the last subtag.
- `Text`: the words of inline HTML — tags left out, character references decoded, white space normalized.
- `Fingerprint`: the SHA-256 of a source with its white space normalized, read back from storage.
- `Segment` and `SegmentState`: a key, a source, its fingerprint, a state from `untranslated` to `reviewed`, a target
  and the source it was written for; `translate()`, `suggest()`, `review()`, `confirm()`, `clear()`, `withKey()`, and
  `withSource()`, which makes a target stale when its source changes and translated again when the source comes back.
- `SourceChange`: what changed in the source of a stale segment, as escaped HTML from `phil/text-diff`.
- `SentenceSplitter`: sentences cut at `.`, `!`, `?`, `…` and the CJK ends, with closing quotes, brackets and tags,
  known abbreviations and initials, tags never cut, the text given back whole.
- `UjoSegmenter`, `Granularity` and `TranslatableFields`: UJO documents cut by text field or by sentence, nested blocks
  included, under keys made of the block identifier and the field path; translated segments put back, the white space
  around each sentence kept, only on the very source they were made from.
- `SegmentSync` and `SyncResult`: stored segments brought up to date with a new cut of their source — kept, moved with
  their translation, stale, added, removed.
- `Variants`: the regional variants that inherit from their parent language, `fr-CA` and `en-CA` by default, their
  chain, and the segments of a variant with the usable targets of its parent where it has none.
- `Memory\TranslationMemory`, `MemoryStore`, `InMemoryStore`, `MemoryUnit`, `MemoryMatch` and `MatchKind`: exact matches
  by fingerprint, fuzzy matches in a window of lengths, sorted by trigrams and scored by their Levenshtein distance,
  one per target, best first, the units of a variant's parent after its own; segments learnt when translated or
  reviewed.
- `Memory\Similarity`: the Levenshtein distance between code points, bounded, counted by PHP's `levenshtein()` on one
  byte per character when the two texts hold 256 different characters at most and in a band of the table otherwise,
  its score from 0 to 1, character trigrams and their Dice coefficient.
- `Glossary\Glossary`, `Term`, `TermHit`, `TermIssue` and `TermIssueKind`: terms found as whole words, the longer of two
  overlapping ones winning, their inflected forms included; targets checked for the count of allowed or kept
  translations and their forms, and for refused ones outside an allowed translation.
- `Glossary\Contract\Inflector` and `RegularPlurals`: the forms an inflector gives added to every term of a glossary;
  the regular plurals of English and French.
- `Check\Contract\Check`, `Checker`, `Checked`, `CheckIssue` and `Flags`: quality checks of translated segments run in
  turn, each dismissed on a segment by its flag `ignore-<check>`; `Punctuation`, `Spaces`, `Tags`, `Plurals` — ICU
  plurals and the CLDR categories of whole numbers — and `SameAsSource`.
- `Check\Contract\TextWidth`, `AdvanceWidths`, `MaxSize` and `Glyphs`: the rendered width of each line of a target in
  pixels from the advances of a font, checked against the segment's `max-size` flag or a limit; the characters the
  character map of a `phil/fonts` face does not cover.
- `Xliff\XliffWriter`: XLIFF 2.1, one unit per segment named after its key, tags as `<ph>` codes with their HTML in
  `<originalData>`, states and `phil:` sub-states, the earlier source of a stale segment in a note.
- `Xliff\XliffReader`: XLIFF 2.0, 2.1, 2.2 and 1.2 read into `XliffFile` and `XliffUnit`, inline codes turned back into
  their HTML, states mapped, document type declarations refused.
- `Xliff\XliffMerge`, `MergeResult`, `Skipped` and `SkipReason`: an imported file merged into segments, its languages
  checked, units of a changed source or without target skipped, tags the source does not hold left out.
- `Tmx\TmxWriter` and `TmxReader`: translation units written as TMX 1.4b, tags as `<bpt>`, `<ept>` and `<ph>`, and read
  back from TMX, document type declarations refused, 100 000 units at most.
- `Tbx\TbxWriter` and `TbxReader`: a glossary written as TBX-Basic of ISO 30042:2019, and the terms of a pair of
  languages read from TBX of 2019 or 2008, statuses mapped, a variant read from its parent's section, document type
  declarations refused, 100 000 entries at most.
- `TranslationMemoryException`, implemented by `InvalidLanguage`, `InvalidSegment`, `InvalidTerm`, `InvalidXliff`,
  `InvalidTmx`, `InvalidTbx` and `InvalidConfiguration`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
