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

- **The chain is declared, never derived.** Stripping the region (`fr_CA → fr`) and then falling back to a default
  looks general and is wrong as soon as two regional variants of a language coexist.
- **A chain that runs out resolves to nothing.** The caller refuses — a 404, `null`, an exception of its own — rather
  than serving a locale nobody declared.
- **A change of language is reported, a change of region is not.** `leavesRequestedLanguage()` lets the application
  count it, or say on the page that it is shown in another language.

A chain resolves what is served; it does not choose which locale was requested. That choice — the request's `_locale`,
an `Accept-Language` negotiation, a user preference — happens before, in the application.

## `DeclaredLocales`

The locales an application serves, from the array it declares: each key a served locale, each value the locales
tried after it, in order.

| Member | Returns |
|---|---|
| `DeclaredLocales::fromArray(array)` | the declared locales; refuses a malformed locale, a fallback that is not served, fallbacks that are not a list, a chain that repeats a locale |
| `all()` | the served locales, in declaration order |
| `isServed(Locale)` | whether the locale is declared |
| `chainFor(Locale)` | its `FallbackChain`; refuses a locale that is not served |

**A fallback must itself be served**: a chain that ends on a locale nobody declared would serve what the application
does not publish.

## `Exception\InvalidLocaleDimension`

An `InvalidArgumentException`, raised by every value refused as it is built. Its message names the value and is meant
for the developer or the administrator who declared it; a bundle that reads a locale from a URL or a form catches it
and answers with a 404 or a form error, or raises its own exception with this one as the previous.
