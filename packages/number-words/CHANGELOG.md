# Changelog

All notable changes to `phil/number-words` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `NumberWords`: cardinals said on their own or agreeing with a gender, ordinals in their gender and number, and
  amounts of money in words with their units and sub-units, in one locale, through ICU's spelling rules; a locale ICU
  does not spell, a missing rule set, digits in ICU's answer and a negative ordinal each refused with an exception.
- `SpellingOptions` and `FrenchSpelling`: French hyphenation in the traditional spelling or in the 1990
  rectifications, the soft hyphens of ICU kept or left out, ICU's verbose forms.
- `CurrencyName`, and the names of ten currencies — `EUR`, `USD`, `CAD`, `AUD`, `NZD`, `GBP`, `CHF`, `JPY`, `MXN`,
  `BRL` — in French, English, Spanish, German, Italian and Portuguese; amounts as decimal strings, never rounded.
- `Contract\LanguageRules`, `Language\AbstractLanguageRules` and the rules of the six languages: the plural of `cents`
  and `quatre-vingts` before large nouns, the French singular of zero and one, `de` or `d'` after `million` and its
  kin, the partitives of Spanish, Italian and Portuguese, the words between units and sub-units and for minus.
- The exceptions under `NumberWordsException`: `IntlUnavailable`, `UnsupportedLocale`, `MissingRule`,
  `NumberOutOfRange` and `InvalidAmount`.
- The unit tests of each language, correction and refusal.
- The documentation in `doc/`, one file per topic, indexed by the README; `CONTRIBUTING.md`, `SECURITY.md` and
  `CODE_OF_CONDUCT.md`.
