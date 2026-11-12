# Exceptions

Every exception implements `Phil\TranslationMemory\Exception\TranslationMemoryException`, and each extends
`InvalidArgumentException`.

| Exception | Thrown when |
|---|---|
| `InvalidLanguage` | A language tag is not BCP 47 as the library reads it; `XliffMerge::merge()` is given a language the file does not have; `Variants` is given a primary language alone |
| `InvalidSegment` | A segment key is empty, longer than 255 bytes or not UTF-8; a text is not UTF-8; state and target disagree; a review or a confirmation is asked of a segment that cannot take it; a fingerprint read back is not one; a memory unit has a source or target without words |
| `InvalidTerm` | A glossary term, translation, refused translation or form has no words, or is not UTF-8 |
| `InvalidXliff` | An XLIFF file is not well-formed XML, declares a document type, is not XLIFF 1.2 or 2.x, has no `<file>` in 1.2, or names a language that is not a tag |
| `InvalidTmx` | A TMX file is not well-formed XML with a `<tmx>` root, declares a document type, lacks its `<header>` or `<body>`, names a language that is not a tag, or holds more than 100 000 units |
| `InvalidTbx` | A TBX file is not well-formed XML with a `<tbx>` or `<martif>` root, declares a document type, names a language that is not a tag, or holds more than 100 000 entries |
| `InvalidConfiguration` | `TranslationMemory` is built with a minimum score not above 0 and at most 1, or a limit or shortlist below 1; a flag is not one, or a segment has more than 32; `AdvanceWidths` or `MaxSize` is given a setting out of range |

The messages name what is wrong:

```text
"fr_CA" is not a language tag such as "en", "fr-CA" or "zh-Hant-TW".
Segment "b1/text" is stale: only a target written for its source can be reviewed.
The XLIFF file cannot be read: a document type declaration is not allowed.
The target language is "fr", but "de" was given.
```
