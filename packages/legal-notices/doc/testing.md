# Testing

| Area | What is covered |
|---|---|
| `Locales` | Locales checked and joined by `_`, a value for a locale, its language or a fallback, texts by locale and their bounds |
| `SiteIdentity`, `Field`, `DocumentType` | Values trimmed, fields left out, refused values, the fields each document lacks |
| `LegalDocumentVersion` | Bounds, the dates of each state, publishing and withdrawing, refused versions |
| `DocumentHistory` | The version in force and the next one, ties on a date, withdrawn and draft versions, refused histories |
| `RetentionDuration`, `DurationWords`, `ConsentCategory` | Durations in words in English and French, refused durations, retentions and categories |
| `Placeholders`, `PlainTextBody` | Values escaped and filled into the text only, never into a tag, a comment or a script, names found, values of a version, paragraphs and line breaks |
| `LegalNotices` | Rendering in a locale, the fallback, a history of another document, versions published in a span, the check |

```sh
vendor/bin/phpunit packages/legal-notices/tests
```

## Fuzzing

Two targets are declared in `phil-dev.php`, with their seeds under `tests/Fuzz/corpus/<target>`:

| Target | What it checks |
|---|---|
| `placeholders` | Rendering plain text and filling placeholders returns a string for any input, and a value is always escaped |
| `durations` | A duration is read into words or refused with `InvalidLegalNotice`, never anything else |

```sh
composer fuzz -- placeholders durations
```
