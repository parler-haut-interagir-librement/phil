# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `NumberWords` | Spells cardinals, ordinals and amounts in one locale, through ICU and the language's rules. |
| `SpellingOptions` | The French spelling, the soft hyphens and the verbose forms. |
| `FrenchSpelling` | `Traditional` or `Rectified1990`. |
| `Gender` | `Masculine`, `Feminine` or `Neuter`: the gender a number agrees with. |
| `Kind` | `Counting`, `Cardinal` or `Ordinal`: what a number is, for the corrections of a language. |
| `CurrencyName` | The names of a currency in one language: unit and sub-unit, singular and plural, their genders, the decimals. |
| `Contract\LanguageRules` | What a language adds to ICU: corrections, currency names and the grammar of amounts. |
| `Language\AbstractLanguageRules` | The common grammar of amounts, for a language to set its words. |
| `Language\French`, `English`, `Spanish`, `German`, `Italian`, `Portuguese` | The rules of the six languages. |
| `Exception\NumberWordsException` | Implemented by every exception of the library. |
| `Exception\IntlUnavailable` | `ext-intl` is not loaded. |
| `Exception\UnsupportedLocale` | ICU does not spell the locale's language, the rules are for another, or the language has no rules for amounts. |
| `Exception\MissingRule` | A rule set, a currency or a sub-unit the language lacks. |
| `Exception\NumberOutOfRange` | A number too large for words, a negative ordinal, a currency with more than 4 decimals. |
| `Exception\InvalidAmount` | An amount that is not a decimal number, or more precise than its currency. |
