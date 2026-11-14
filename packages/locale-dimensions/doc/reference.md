# Reference

Every public type of the library, under `Phil\LocaleDimensions`. Each is a final, immutable value object built by a
named constructor that refuses what cannot exist with `Exception\InvalidLocaleDimension`.

| Type | Role |
|---|---|
| `Locale` | A language, optionally narrowed to a region |
| `Version` | A version counted from one |
| `FallbackChain` | The ordered locales tried for one requested locale |
| `DeclaredLocales` | The locales an application serves, each with its chain |
| `Exception\InvalidLocaleDimension` | A value refused as it is built |

## `Locale`

`Locale::fromString(string)` accepts **one spelling only**: a language of two or three lowercase letters, optionally an
underscore and a region of two uppercase letters (`fr`, `fil`, `fr_CA`), as Symfony and `intl` write it. Rows are
found by equality on this string, so admitting `fr-CA` or `fr_ca` beside `fr_CA` would create rows a lookup misses.
Anything else is refused, never normalized.

| Member | Returns |
|---|---|
| `$value`, `$language`, `$region` | `fr_CA`, `fr`, `CA` (`null` without a region) |
| `sharesLanguageWith(Locale)` | whether the two have the same language: `fr` and `fr_CA` do, `fr_CA` and `en_CA` do not |
| `toLanguageTag()` | the BCP 47 tag, `fr-CA`, which `hreflang`, `lang` and `Content-Language` read |
| `equals(Locale)`, `toString()`, `__toString()` | comparison and string form |
| `Locale::tryFromString(string)` | the locale, or `null` for a string that cannot be one |

`tryFromString()` is for a locale read from outside — a URL, a form, an `Accept-Language` header, a stored row —
where a refusal is an answer, a 404 or a form error, rather than a fault. Its spelling is narrower than the characters
Symfony's `LocaleFallbackProvider::validateLocale()` admits, so a locale it accepts is one the translator accepts too.

Which locales exist is the application's decision; `Locale` only refuses what cannot be one. Scripts (`zh_Hant`) and
numeric regions (`es_419`) are not accepted.

## `Version`

`Version` counts from one: `Version::first()`, `Version::of(int)`, `next()`, `isAfter(Version)`, `equals(Version)`, and
the public `$number`. `Version::of()` refuses zero and negative numbers, whatever their source: a version zero would
read as "no version yet" in one place and as a real row in another.

**A published version is never edited.** Editing it forks the next version, and the published one stays as it was
approved or sent. Which event freezes a version is the application's decision; the numbering is not.

## `FallbackChain`

The ordered list of locales tried for one requested locale, the requested one first.

| Member | Returns |
|---|---|
| `FallbackChain::of(array)` | the chain; refuses an empty list and a locale that appears twice |
| `$locales` | the locales, the requested one first |
| `requested()` | the requested locale |
| `resolve(iterable)` | the first locale of the chain among the given ones, or `null` |
| `leavesRequestedLanguage(Locale)` | whether serving that locale leaves the requested language |
| `toString()` | `fr_CA → fr → en` |

- **The chain is declared.** Stripping the region (`fr_CA → fr`) and then falling back to a default looks general and
  is wrong as soon as two regional variants of a language coexist. A locale that declares no chain gets the default
  one of `DeclaredLocales`, limited to the served locales.
- **A chain that runs out resolves to nothing.** The caller refuses — a 404, `null`, an exception of its own — rather
  than serving a locale nobody declared.
- **A change of language is reported, a change of region is not.** `leavesRequestedLanguage()` lets the application
  count it, or say on the page that it is shown in another language.

A chain resolves what is served; it does not choose which locale was requested. That choice — the request's `_locale`,
an `Accept-Language` negotiation, a user preference — happens before, in the application.

## `DeclaredLocales`

The locales an application serves, from the array it declares: each key a served locale, each value the locales
tried after it, in order, or `null` for the default chain.

| Member | Returns |
|---|---|
| `DeclaredLocales::fromArray(array, array $defaultFallbacks = [])` | the declared locales; refuses a malformed locale, a fallback or a default fallback that is not served, fallbacks that are neither a list nor `null`, a chain that repeats a locale |
| `all()` | the served locales, in declaration order |
| `isServed(Locale)` | whether the locale is declared |
| `chainFor(Locale)` | its `FallbackChain`; refuses a locale that is not served |

**A fallback must itself be served**: a chain that ends on a locale nobody declared would serve what the application
does not publish.

**The default chain.** A locale declared with `null` gets the parents that Symfony's `LocaleFallbackProvider` derives
from ICU data — `fr_CA → fr`, `en_CA → en`, `es_MX → es` —, then the default fallbacks in their order, each kept only
when it is served and never the locale itself. It is the chain Symfony's translator walks, cut to what the application
publishes. An empty list is a chain with no fallback:

```php
$locales = DeclaredLocales::fromArray(['fr_CA' => null, 'pt_BR' => null, 'fr' => [], 'en' => []], ['en']);
$locales->chainFor(Locale::fromString('fr_CA'))->toString();   // 'fr_CA → fr → en'
$locales->chainFor(Locale::fromString('pt_BR'))->toString();   // 'pt_BR → en': pt is not served
$locales->chainFor(Locale::fromString('fr'))->toString();      // 'fr'
```

## `Exception\InvalidLocaleDimension`

An `InvalidArgumentException`, raised by every value refused as it is built. Its message names the value and is meant
for the developer or the administrator who declared it; a bundle that reads a locale from a URL or a form catches it
and answers with a 404 or a form error, or raises its own exception with this one as the previous.
