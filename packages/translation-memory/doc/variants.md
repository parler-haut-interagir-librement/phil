# Regional variants

A regional variant is translated as a set of departures from its parent language. A `fr-CA` segment without a usable
target of its own takes the `fr` one; translators of Canadian French only write the segments that differ in Canada —
`courriel` for `e-mail`, `fin de semaine` for `week-end` —, and every other segment reads as French.

`Variants` names the variants that inherit. By default they are `fr-CA` and `en-CA`; any other tag stands alone.

```php
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Variants;

$variants = new Variants();                          // fr-CA and en-CA
$variants = new Variants(['fr-CA', 'en-CA', 'pt-BR']);
$variants = Variants::none();                        // every language stands alone

$variants->parentOf(LanguageTag::of('fr-CA'));       // fr
$variants->chain(LanguageTag::of('fr-CA'));          // [fr-CA, fr]
$variants->variants();                               // ['fr-CA', 'en-CA']
```

A locale as Symfony writes it, `fr_CA`, is read by `LanguageTag::fromLocale()`. The parent of a tag is the tag without
its last subtag: `LanguageTag::of('fr-CA')->parent()` is `fr`,
`zh-Hant-TW` gives `zh-Hant`. A variant whose parent is itself a variant inherits along the chain, the nearest language
winning. A primary language alone has no parent and is refused as a variant with `InvalidLanguage`.

## Inheriting targets

`inherit($own, $parent)` gives the segments of a variant with the targets of its parent filled in:

- a segment of the variant that is translated or reviewed keeps its own target;
- one that is untranslated, suggested or stale — or missing — takes the parent's segment of the same key, when that
  one is translated or reviewed and was made from the same source, white space aside;
- the variant's own segments come first, in their order, then the parent's usable segments it lacks.

```php
$french = $segmenter->assemble($document, $variants->inherit($canadianSegments, $frenchSegments));
```

For a longer chain, resolve from the farthest language in: the parent's segments given to `inherit()` are those of the
parent already resolved against its own parent. `phil/translation-bundle` does this for the document a site publishes,
and keeps the variant's own segments for the translators' screen.

## The memory

`TranslationMemory` reads along the same chain: a lookup for `fr-CA` gives the `fr-CA` units and the `fr` units, best
score first, and a `fr-CA` unit before a `fr` unit of the same score. The memory is given its `Variants` as its fifth
argument, `new Variants()` by default. See [Translation memory](memory.md).
