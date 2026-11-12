# Translating documents

`Phil\TranslationBundle\Application\Translations` translates the application's documents segment by segment, in the
bundle's stores. A document is named by a reference of 1 to 255 bytes chosen by the application — `article:42` — and
translated into any number of target languages, named by BCP 47 tags.

The bundle translates documents in UJO (Universal JSON Output) only. The message catalogues of Symfony — the
`messages+intl-icu` files of an application's interface — stay with the tools made for them: Symfony's translation
component, its extractors and its translation providers.

## Preparing

```php
$result = $translations->prepare('article:42', $article->content(), LanguageTag::of('en'), LanguageTag::of('fr'));
```

The source is cut into segments by the configured granularity, and the stored segments of the document in that target
language are brought up to date: kept, moved with their translation, made stale, added or removed — the `SyncResult`
lists each. When `prefill` is on, each new untranslated segment is proposed the exact match of the memory, if any, as
`suggested`. Rows are written in the order of the source.

Prepare a document before it is changed or imported — the screen does on each `POST` — and after its source is saved,
so that stale segments show at once.

```php
$result = $translations->preview('article:42', $article->content(), LanguageTag::of('en'), LanguageTag::of('fr'));
$xml = $translations->exportPreview('article:42', $article->content(), LanguageTag::of('en'), LanguageTag::of('fr'));
```

`preview()` gives the `SyncResult` a preparation would give, and `exportPreview()` the XLIFF 2.1 file of those
segments, without writing a row or touching the stored ones: what a request that only reads shows. The screen's page
and export are such `GET` requests.

## Acting on a segment

| Method | Result |
|---|---|
| `translate($document, $language, $key, $target)` | `translated`, the target written for the current source |
| `suggest($document, $language, $key, $target)` | `suggested` |
| `review($document, $language, $key)` | `reviewed`; refused on an untranslated or stale segment |
| `confirm($document, $language, $key)` | `translated` again, for a stale segment whose target still holds |
| `clear($document, $language, $key)` | `untranslated` |

Each returns the new `Segment`, throws `NotFound` for a key the document does not have, and lets `InvalidSegment`
through for a change the segment cannot take. A target that becomes usable — translated, reviewed or confirmed — is
learnt by the memory, its origin the document. Each also writes who wrote and who reviewed the target, as
[Comments and credits](collaboration.md#who-did-what) lists, and clears the mark of machine output when a person writes
a new target. `records($document, $language)` gives the rows by segment key, with `translatedBy()`, `reviewedBy()`,
`isMachine()` and `flags()`.

`segments()` gives the segments of a document in order, `segment()` one of them, and `progress()` a `Progress`: the
count of each state, the usable segments and their share as a whole percentage.

## Matches, terms and issues

`suggestions()` gives what the memory proposes for a segment, `terms()` the glossary terms of its source, `issues()`
the glossary issues of its target — none without a target. For many segments at once, `matches()` takes a segment at
hand, and `glossary()` gives the glossary of the pair of languages to `find()` and `check()` with.

## Quality checks

`check($segment, $sourceLanguage, $targetLanguage, $flags)` runs the quality checks of `phil/translation-memory` on a
segment at hand — punctuation, spaces, tags, ICU plurals and targets identical to their source — and gives their
`CheckIssue`s; none without a target. The flags of a segment are kept with its row:

```php
$translations->dismiss('article:42', LanguageTag::of('fr'), 'b1/text', 'same');                 // adds ignore-same
$translations->flag('article:42', LanguageTag::of('fr'), 'b1/text', Flags::parse('max-size:240'));
```

`dismiss()` adds the flag `ignore-<check>`, and `flag()` replaces the flags; written, they are at most 1024 bytes, or
`InvalidRecord` is thrown. A name that is not a flag lets the library's `InvalidConfiguration` through. Given a
`Checker` as its last argument, `Translations` runs those checks instead of the library's defaults — with a
`MaxSize` of the application's font, say.

## XLIFF

```php
$xml = $translations->export('article:42', LanguageTag::of('fr'));
$report = $translations->import('article:42', LanguageTag::of('fr'), $uploadedXml, canReview: $policy->canReview('article:42', LanguageTag::of('fr')));
```

`export()` writes the segments of a prepared document as XLIFF 2.1; `import()` reads XLIFF 2.x or 1.2, checks that the
file is of the document's languages, merges it, writes the segments it changed and learns those it made usable. The
`MergeResult` lists the keys applied and the units skipped with their reason. Both throw `NotFound` for a document not
prepared in the language; `import()` lets through `InvalidXliff` and `InvalidLanguage` from the library.

`import()` merges a unit marked `final` or `signed-off` as reviewed only when `canReview` is `true`; by default, and
for whoever may not review, it merges it as translated, its target kept, and the `MergeResult` says so. The screen
passes `canReview()` of the policy.

## Machine translation

`machineTranslate()` proposes the targets of the configured machine translator to the untranslated segments; see
[Machine translation](machine-translation.md).

## Credits

`credits()` gives the people thanked for the translation of a document; see
[Comments and credits](collaboration.md#public-credit).

## The translated document

```php
$french = $translations->translated('article:42', $article->content(), LanguageTag::of('fr'));
$preview = $translations->translated('article:42', $article->content(), LanguageTag::of('fr'), [SegmentState::Translated, SegmentState::Reviewed, SegmentState::Suggested]);
```

Each piece of the source is replaced by the target of its segment when the segment is in an accepted state —
translated or reviewed by default — and was made from that very text; every other piece stays in the source language.
For a regional variant, `translated()` reads along its chain — `fr-CA`, then `fr`, then the source — and `raw()` reads
the variant's own segments only; see [Regional variants](variants.md).
The result is a UJO document to render through `phil/ujo` or `phil/ujo-bundle`, whose sanitizer keeps only the inline
tags it allows.
