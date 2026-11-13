# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Spell amounts in another language, or change the words of one of the six | Implement `Contract\LanguageRules`, or extend `Language\AbstractLanguageRules`, and pass it to `NumberWords` |
| Name a currency the rules do not name, or name one differently | Pass a `CurrencyName` to `amount()` |
| Keep soft hyphens, say `and` in English, choose the French spelling | Pass `SpellingOptions` |

`AbstractLanguageRules` holds the common grammar: a table of currencies, the word between units and sub-units, the
word for minus, the singular at one, and an optional partitive after the nouns of large numbers. A class that extends
it sets these; `correct()` receives ICU's words with the `Kind` of the number and may rewrite them.

```php
use Phil\NumberWords\Gender;
use Phil\NumberWords\Language\AbstractLanguageRules;
use Phil\NumberWords\NumberWords;

final class Dutch extends AbstractLanguageRules
{
    public function language(): string { return 'nl'; }
    protected function currencies(): array
    {
        return ['EUR' => ['euro', 'euro', Gender::Masculine, 2, 'cent', 'cent', Gender::Masculine]];
    }
    protected function connector(): string { return 'en'; }
    protected function minus(): string { return 'min'; }
}

new NumberWords('nl', rules: new Dutch())->amount('21.05', 'EUR'); // eenentwintig euro en vijf cent
```

The rules passed must be for the locale's language, or `NumberWords` throws `UnsupportedLocale`. The spelling itself
stays ICU's: the rules correct its words, they do not replace its rule sets.
