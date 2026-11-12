# Roots, dimensions and fallback chains

A piece of content is a **root** and its **dimension rows**. The root holds what stays the same across every
locale, stage and version; a row holds the values of one locale (or of none, for the shared row), one stage and
one version.

```text
News (root, id 0192f3c4-…)
├── */draft/1        the unlocalized row: values every locale shares
├── fr/draft/1       the French draft, read over */draft/1
├── en/draft/1
├── */live/1         copies made by publishing
└── fr/live/1
```

## The dimension

`Phil\ContentBundle\Domain\Dimension\DimensionKey` identifies a row under its root. It is a final readonly
class with three public properties, `?Locale $locale`, `Stage $stage` and `Version $version`, built by two named
constructors. `Locale`, `Version`, `FallbackChain` and `DeclaredLocales` are the value objects of
`phil/locale-dimensions`, which the bundle requires; `DimensionKey` and `Stage` are the bundle's own.

```php
use Phil\ContentBundle\Domain\Dimension\{DimensionKey, Stage};
use Phil\LocaleDimensions\{Locale, Version};

$shared = DimensionKey::unlocalized(Stage::Draft, Version::first());
$french = DimensionKey::localized(Locale::fromString('fr'), Stage::Draft, Version::first());

$french->isLocalized();              // true
$french->unlocalizedCounterpart();   // */draft/1: the row it is read over
$french->inStage(Stage::Live);       // fr/live/1: where publishing writes it
$french->atVersion(Version::of(2));  // fr/draft/2: where a fork writes it
$french->equals($shared);            // false
$french->toString();                 // "fr/draft/1"; "*/draft/1" for the shared row
```

### Invariants

- **The unlocalized row holds the shared values**, written once instead of once per language, so they cannot
  drift apart: a template key, an excerpt image, a date. A localized row is always read over the unlocalized
  row of the same stage and version. The unlocalized row is its own counterpart.
- **A row's dimension never changes once it is built.** A row of another locale, stage or version is another
  row; the base class has no setter for it.
- **No collection on the root.** Each row points to its root, and rows are loaded through
  `DimensionContentRepository`, one dimension at a time. A root that held its rows would load every version of
  every locale to serve one page, and would put a mapped collection in the domain.

## Locale

`Locale::fromString(string)` accepts **one spelling only**: a language of two or three lowercase letters,
optionally an underscore and a region of two uppercase letters (`fr`, `fr_CA`). Rows are found by equality on
this string, so admitting `fr-CA` or `fr_ca` beside `fr_CA` would create rows a lookup misses. Anything else
throws `InvalidLocaleDimension`; through the bundle — `ContentManager`, the controllers, the Twig functions,
`DimensionLocales::parse()` — the refusal reaches the caller as `InvalidDimension`, a `DomainError`, with the
original as its previous exception.

| Member | Returns |
|---|---|
| `$value`, `$language`, `$region` | `fr_CA`, `fr`, `CA` (`null` without a region) |
| `sharesLanguageWith(Locale)` | whether the two have the same language: `fr` and `fr_CA` do, `fr_CA` and `en_CA` do not |
| `toLanguageTag()` | the BCP 47 form, `fr-CA`, for `hreflang` and `lang` |
| `equals(Locale)`, `toString()`, `__toString()` | comparison and string form |

Which locales exist is the application's decision (`phil_content.locales`); `Locale` only refuses what cannot
be one.

## Stage

`Stage` is a backed enum: `Stage::Draft` (`draft`) is what editors change, `Stage::Live` (`live`) is what
readers are served. Publishing copies a draft row onto its live twin, so the two never share a row and an edit
in progress never reaches a reader ([publication.md](publication.md)).

## Version

`Version` counts from one: `Version::first()`, `Version::of(int)` (zero and negative numbers throw
`InvalidLocaleDimension`, whatever their source), `next()`, `isAfter(Version)`, `equals(Version)`, and the public
`$number`.

**A published version is never edited.** Editing it forks the next version, and the published one stays as it
was approved. The rules of freezing and forking are in [publication.md](publication.md#versions).

## Fallback chains

`FallbackChain` is the ordered list of locales tried for one requested locale, the requested one first.

```php
use Phil\LocaleDimensions\{FallbackChain, Locale};

$chain = FallbackChain::of([Locale::fromString('fr_CA'), Locale::fromString('fr')]);

$chain->requested();                                   // fr_CA
$chain->resolve([Locale::fromString('fr')]);           // fr: the first locale of the chain that has content
$chain->resolve([Locale::fromString('en')]);           // null: nothing catches the last step
$chain->leavesRequestedLanguage(Locale::fromString('fr'));   // false: same language
$chain->toString();                                    // "fr_CA → fr"
```

- **The chain is declared, never derived.** Stripping the region (`fr_CA → fr`) and then falling back to a
  default looks general and is wrong as soon as two regional variants of a language coexist. The application
  writes the chain of each locale it serves under `phil_content.locales`, and `DeclaredLocales` builds them.
- **A chain that runs out resolves to nothing.** The caller refuses (404, `null`, `ContentNotFound`) rather than
  serving a locale nobody declared.
- **A change of language is reported, a change of region is not.** `leavesRequestedLanguage()` tells when the
  locale served is in another language than the one requested, so the application can count or announce it; the
  controller passes it to the template as `left_language`.
- `FallbackChain::of()` refuses an empty list and a locale that appears twice (`InvalidLocaleDimension`).

A chain resolves content; it does not choose which locale was requested. That choice — the request's
`_locale`, an `Accept-Language` negotiation, a user preference — happens before, in the application.

## Declared locales

`Phil\LocaleDimensions\DeclaredLocales` is the service built from `phil_content.locales`:

| Method | Returns |
|---|---|
| `DeclaredLocales::fromArray(array)` | the declared locales; throws `InvalidLocaleDimension` for a malformed locale, a fallback that is not served, or a chain that repeats a locale |
| `all()` | the served locales, in declaration order |
| `isServed(Locale)` | whether the locale is declared |
| `chainFor(Locale)` | its `FallbackChain`; throws `InvalidLocaleDimension` when the locale is not served |

`Phil\ContentBundle\Domain\Dimension\DimensionLocales` is what the bundle and the bundles built on it call with a
locale typed in a URL, a form or a command: `parse(string)` and `chain(DeclaredLocales, Locale)` do the same work
and raise `InvalidDimension` instead, so that one `catch (DomainError)` covers a refused locale like any other
refused value.

## Reading one dimension

`ContentAggregator` reads one dimension of a root as a single row, merged from the unlocalized row and the
localized row ([pipeline.md](pipeline.md)):

- `aggregate(ContentRoot, DimensionKey)` — for a localized key, the localized row merged over its shared row;
  **a requested locale without its own row is not found** (`ContentNotFound`), even when the shared row exists,
  since serving the shared values alone would publish a page in a language nobody wrote. A missing shared row
  alone is not an error. For an unlocalized key, the unlocalized row alone.
- `aggregateAlong(ContentRoot, FallbackChain, Stage, Version)` — the first locale of the chain that has a row
  in that stage and version, merged. The merged row's `dimension()->locale` says which locale was served.

What a reader is served is `LiveContentReader::read(ContentRoot, FallbackChain)`, which walks the chain over the
live stage, each locale in the version it was last published from ([publication.md](publication.md)).
