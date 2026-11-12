# Regional variants

A regional variant is translated as the departures from its parent language: Canadian French writes only the
segments that differ from French — `courriel`, `fin de semaine` — and reads every other one in French. The
`variants` option names the variants, as BCP 47 tags; by default `fr-CA` and `en-CA`.

```yaml
phil_translation:
    variants: [fr-CA, en-CA]     # [] for none: every language stands alone
```

A locale as Symfony writes it, `fr_CA`, is read with `LanguageTag::fromLocale($request->getLocale())`.

## The chain

Each variant has a chain: itself, its parent, and the source. A `fr-CA` document is read from its own segments first,
then from the `fr` segments where `fr-CA` has no usable target, then from the source language where neither has one.
A parent target is taken only when it is translated or reviewed and was made from the same source text: a `fr`
target written for an older source is not inherited.

The memory reads along the same chain: a `fr-CA` segment is proposed the `fr-CA` units first and the `fr` units after,
and a new `fr-CA` segment is prefilled with the exact `fr` match when it has none of its own.

## Two readings

| Reading | Method | For |
|---|---|---|
| Translated, through the chain | `translated($document, $source, $language)` | The site that publishes the document |
| Raw, the language's own segments | `raw($document, $source, $language)` | The administration, to see what a variant changes |

```php
$published = $translations->translated('article:42', $article->content(), LanguageTag::fromLocale('fr_CA'));
$canadianOnly = $translations->raw('article:42', $article->content(), LanguageTag::of('fr-CA'));
```

`resolved($document, $language)` gives the segments the translated reading uses: the variant's own, with the usable
targets of its chain where it has none. `parentOf($language)` names the parent of a variant, `null` for a language that
is not one.

For a language that is not a variant both readings are the same: its own segments, then the source.

## On the screen

The page of a variant says which language it inherits from, and each segment without a usable target of its own shows
the target it inherits — `From fr: …` — so that the translator writes a `fr-CA` target only where Canada differs.
