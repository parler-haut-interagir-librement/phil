# Extension points

The library is plain PHP and declares no service. Its ports are where the translation memory is kept, the quality
checks, how wide a line is drawn, and the inflected forms of glossary terms; the rest is chosen through constructor
arguments.

## `MemoryStore`

`Phil\TranslationMemory\Memory\MemoryStore` — where translation units are kept. `InMemoryStore` keeps them in an
array. See [Translation memory](memory.md#units-and-stores).

| Method | Contract |
|---|---|
| `exact(LanguageTag $source, LanguageTag $target, Fingerprint $fingerprint): iterable<MemoryUnit>` | The units of that pair of languages whose source has that fingerprint |
| `candidates(LanguageTag $source, LanguageTag $target, int $minLength, int $maxLength): iterable<MemoryUnit>` | The units of that pair whose plain source is between the two lengths, in characters |
| `add(MemoryUnit $unit): void` | Keeps the unit; one of the same languages and fingerprint replaces the earlier one |

A store over a database indexes the pair of languages with the fingerprint, and with the length: fuzzy matching reads
only the candidates it is given.

## `Check`

`Phil\TranslationMemory\Check\Contract\Check` — one quality check of a translated segment. The library's are
`Punctuation`, `Spaces`, `Tags`, `Plurals`, `SameAsSource`, `MaxSize` and `Glyphs`. See [Quality checks](checks.md).

| Method | Contract |
|---|---|
| `name(): string` | Lower case letters and hyphens; the flag `ignore-<name>` dismisses the check |
| `check(Checked $checked): list<CheckIssue>` | The issues of the target against its source, none when it passes; no state kept between calls |

## `TextWidth`

`Phil\TranslationMemory\Check\Contract\TextWidth` — how wide a line of plain text is drawn, in pixels, for `MaxSize`.
`AdvanceWidths` sums the advances of a font; a renderer's own measure — a PDF engine, a browser — fits here too.

| Method | Contract |
|---|---|
| `width(string $text): float` | The width of one line of plain text, in pixels |

## `Inflector`

`Phil\TranslationMemory\Glossary\Contract\Inflector` — the inflected forms of a phrase of a glossary. `RegularPlurals`
gives the regular plurals of English and French. See [Glossary](glossary.md#inflected-forms).

| Method | Contract |
|---|---|
| `forms(string $phrase, LanguageTag $language): list<string>` | The other forms of the phrase, without itself; none for a language it does not know |

## Arguments

| Point | How |
|---|---|
| How fuzzy a match may be | The `$minimum` score, the `$limit` of matches and the `$shortlist` of candidates scored, given to `TranslationMemory` |
| How documents are cut | The `Granularity` of `UjoSegmenter`: by block field or by sentence |
| Which fields are translated | `TranslatableFields`: the field names translated and the block types left alone, the application's own blocks included |
| Where sentences end | The `SentenceSplitter` given to `UjoSegmenter`, with the abbreviations that end no sentence |
| The terms of a pair of languages | The `Term`s given to `Glossary`, from the application's storage, and their forms |
| The regional variants | The `Variants` given to `TranslationMemory`, and used to inherit segments: `fr-CA` and `en-CA` by default |
| The checks run | The `Check`s given to `Checker`, or `Checker::defaults()` with a `TextWidth`, a limit and a font |
| The checks of one segment | Its `Flags`: `ignore-<check>`, `max-size:<pixels>` |
