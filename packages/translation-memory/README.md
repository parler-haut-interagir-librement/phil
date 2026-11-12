# phil/translation-memory

**Translate documents segment by segment, reuse what was translated before, and keep terms consistent.**

phil/translation-memory cuts a document into segments — one per text field of each block of a UJO document, or one per
sentence —, gives each segment a fingerprint of its source and a state, from `untranslated` to `reviewed`, and makes it
`stale` when its source changes under a translation, without losing that translation. A regional variant such as
`fr-CA` inherits the targets of its parent language and is translated only where it differs. A translation memory
proposes what was translated before, exact or close, scored from 0 to 1. A glossary finds the terms of a source, their
inflected forms included, and checks that the target uses their allowed translations and none of the refused ones.
Quality checks report a lost full stop, a missing tag, a plural form the language needs, a target copied from its
source, or a label drawn wider than its place, in pixels. Segments leave for a translation tool as XLIFF 2.1 and come
back from XLIFF 2.x or 1.2; the memory travels as TMX and the glossary as TBX. The translated segments are put back
into the document.

It is for any PHP application that publishes in more than one language what people write in blocks: articles, pages,
rules, books, help. The translators work segment by segment, the memory saves them from translating twice the same
sentence, the glossary keeps the vocabulary of a game or a product, and when an author edits the source only the
segments that changed need work again.

It is a plain PHP library whose requirements are `ext-ctype`, `ext-dom`, `ext-libxml`, `ext-mbstring`, `phil/ujo` —
UJO (Universal JSON Output), the block document format —, `phil/text-diff`, which shows what changed in the source of a
stale segment, and `phil/fonts`, which reads the character map of a template's font. Nothing is stored by the library:
the application keeps segments where it likes, and gives the memory a store, in memory or its own;
`phil/translation-bundle` keeps both in Doctrine and adds a translation screen.

The design is small: a `Segment` is a value with a key, a source, a `Fingerprint`, a `SegmentState` and a target;
`UjoSegmenter` cuts a document into `SourceSegment`s, with a `SentenceSplitter` when cutting by sentence, and puts
segments back; `SegmentSync` brings stored segments up to date with a new source; `Variants` lets a regional variant
inherit from its parent; `TranslationMemory` looks up a `MemoryStore` and scores with `Similarity`; a `Glossary` of
`Term`s finds and checks, with the forms of an `Inflector`; a `Checker` runs the `Check`s of the `Check` namespace;
`XliffWriter`, `XliffReader` and `XliffMerge` exchange segments, `TmxWriter` and `TmxReader` the memory, `TbxWriter` and
`TbxReader` the glossary.

## Status

| Part | State |
|---|---|
| Segments: key, source, fingerprint, state, target, and the source the target was written for | Implemented and tested |
| States `untranslated`, `suggested`, `translated`, `reviewed`, `stale`, and their transitions | Implemented and tested |
| UJO documents cut by text field or by sentence, nested blocks included, and translated segments put back | Implemented and tested |
| Sentences: ends, closing quotes and tags, abbreviations, initials, CJK ends | Implemented and tested |
| Stored segments brought up to date: kept, moved, stale, added, removed | Implemented and tested |
| What changed in the source of a stale segment, by `phil/text-diff` | Implemented and tested |
| Translation memory: exact matches, fuzzy matches by Levenshtein distance and trigrams, a store in memory | Implemented and tested |
| Regional variants inheriting from their parent, `fr-CA` and `en-CA` by default, in segments and memory | Implemented and tested |
| Glossary: terms found as whole words, translations allowed, kept and refused, checked by count | Implemented and tested |
| Inflected forms of glossary terms, written or made by an inflector; regular plurals of English and French | Implemented and tested |
| Quality checks: punctuation, spaces, tags, ICU plurals, identical to the source, each dismissed by a flag | Implemented and tested |
| Rendered size in pixels from the advances of a font; the characters a `phil/fonts` face draws | Implemented and tested |
| XLIFF 2.1 written, with tags as inline codes; XLIFF 2.0, 2.1, 2.2 and 1.2 read; files merged into segments | Implemented and tested |
| TMX 1.4b written and read; TBX of 2019 written, TBX of 2019 and 2008 read | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Segments](doc/segments.md) | `Segment`, fingerprints, states and their transitions, stale segments and what changed. |
| [Segmentation](doc/segmentation.md) | Cutting a UJO document by field or by sentence, keys, putting segments back, synchronizing. |
| [Translation memory](doc/memory.md) | `TranslationMemory`, exact and fuzzy matches, scores, stores, learning. |
| [Regional variants](doc/variants.md) | `Variants`, parent languages, inherited targets, the memory of a variant. |
| [Glossary](doc/glossary.md) | Terms, allowed, kept and refused translations, inflected forms, finding and checking. |
| [Quality checks](doc/checks.md) | The checks, plural categories, flags, the rendered size in pixels, writing a check. |
| [XLIFF](doc/xliff.md) | Export in XLIFF 2.1, import of XLIFF 2.x and 1.2, merging, what is refused. |
| [TMX and TBX](doc/tmx-and-tbx.md) | The memory as TMX, the glossary as TBX, what is read and what is refused. |
| [Exceptions](doc/exceptions.md) | `TranslationMemoryException` and its exceptions. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that translates. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/translation-memory
```

```php
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Segment;
use Phil\TranslationMemory\SegmentSync;
use Phil\TranslationMemory\Segmentation\Granularity;
use Phil\TranslationMemory\Segmentation\UjoSegmenter;
use Phil\TranslationMemory\Xliff\XliffFile;
use Phil\TranslationMemory\Xliff\XliffWriter;
use Phil\Ujo\Ujo;

$document = new Ujo()->decode($json);
$segmenter = new UjoSegmenter(Granularity::Sentence);

$result = SegmentSync::sync($storedSegments, $segmenter->segment($document));
$segments = $result->segments;                       // stale, moved, added and removed are listed too

$segments[0] = $segments[0]->translate('Bonjour le monde.');

$xliff = new XliffWriter()->write(XliffFile::fromSegments('page:42', LanguageTag::of('en'), LanguageTag::of('fr'), $segments));
$french = $segmenter->assemble($document, $segments);  // a UJO document, untranslated pieces kept in English
```

## Requirements

PHP 8.5 or later, `ext-ctype`, `ext-dom`, `ext-libxml`, `ext-mbstring`, `phil/ujo`, `phil/text-diff` and `phil/fonts`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
