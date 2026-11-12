# Translation memory

`Memory\TranslationMemory` proposes what was translated before for a new source.

```php
$memory = new TranslationMemory($store, minimum: 0.7, limit: 5, shortlist: 50);

foreach ($memory->lookup('The quick brown fox!', LanguageTag::of('en'), LanguageTag::of('fr')) as $match) {
    printf("%s %d%% %s\n", $match->kind->value, $match->percent(), $match->unit->target);
}
// fuzzy 95% Le renard brun rapide
```

## Matches

| Kind | Found by | Score |
|---|---|---|
| `exact` | The same source fingerprint — the same text, white space aside | 1 |
| `fuzzy` | A close plain text | From the minimum to 0.99 |

A fuzzy match is found in three steps:

1. **Lengths.** The score of two texts is `1 - distance / length of the longer`, so a text of `n` characters can only
   reach the minimum `m` against texts of `⌈n·m⌉` to `⌊n/m⌋` characters. The store gives those only.
2. **Trigrams.** The candidates are sorted by the Dice coefficient of their character trigrams — read in lower case,
   padded with a space —, and the best `shortlist` are kept.
3. **Levenshtein.** Each is scored by the Levenshtein distance between the code points of both plain texts. When the
   two texts hold 256 different characters at most — nearly always —, each character is written as one byte and PHP's
   own `levenshtein()` counts the edits; otherwise the table is computed in a band as wide as the distance the minimum
   allows, and abandoned as soon as a whole row is past it.

The same words with other tags score 0.99, below every exact match. Matches come best first, one per target text — the
best — and at most `limit`. A minimum of 1 keeps exact matches only. `MemoryMatch::percent()` gives the score as a
whole percentage rounded down, as translation tools show it.

A regional variant reads the units of its parent too: a lookup for `fr-CA` gives the `fr-CA` and the `fr` units, a
`fr-CA` unit before a `fr` unit of the same score. The memory's fifth argument, `Variants`, names the variants —
`fr-CA` and `en-CA` by default, `Variants::none()` for none. See [Regional variants](variants.md).

`Similarity` is public: `distance()`, `score()`, `trigrams()` and `dice()` serve any comparison of texts.

## Units and stores

A `MemoryUnit` pairs a source in one language with its target in another, and says where it came from — a document,
a file — in `origin`. Both texts are inline HTML and must hold words. The unit knows its source fingerprint, its plain
text and its length in characters.

A `MemoryStore` answers two questions — `exact()`, the units of a pair of languages and a fingerprint, and
`candidates()`, those whose plain source is between two lengths — and keeps units with `add()`: a unit of the same
languages and fingerprint replaces the earlier one, the latest translation winning. `InMemoryStore` keeps them in an
array; it is countable. An application's store over its database indexes the pair of languages with the fingerprint,
and with the length.

## Learning

```php
$memory->learn($segment, LanguageTag::of('en'), LanguageTag::of('fr'), origin: 'page:42');
```

A segment is kept when it is translated or reviewed and both its source and its target hold words; `learn()` says
whether it was.
