# Testing

| Area | What is covered |
|---|---|
| `Slug`, `InvalidSlug` | Every rule and bound, equality, the spelling given back |
| `TransliteratingSlugger` | Accents, ligatures, apostrophes, digits, the locale, the cut at a word and its bounds, a text with nothing to slug |
| `LeadingWordsRemoved` | One word dropped once, a slug of one word kept, the decorated slugger's slug, the words checked, with or without a slugger |
| `Sluggers`, `UnknownSlugger` | By name and by default, the names, an unknown name and an unknown default |

```sh
vendor/bin/phpunit packages/slug/tests
```
