# Plain data

`Statement::toArray()` writes a statement as plain data, and `StatementReader::fromArray()` reads it back to the same
statement. `StatementReader::fromJson()` reads the same data as JSON, up to 1 MiB and 16 levels deep. A configuration
file, a database row or an API carries a statement this way.

```php
use Phil\AccessibilityStatement\StatementReader;

$statement = StatementReader::fromArray([
    'version' => 2,
    'published_at' => '2026-11-08',
    'entity' => 'Example Org',
    'feedback' => ['email' => 'accessibility@example.com'],
    'non_conformities' => [
        ['criterion' => '1.4.3', 'description' => ['en' => 'Grey text on the event pages.']],
    ],
]);

$statement->status;     // ConformanceStatus::Partial, derived from the non-conformities
$statement->target;     // WCAG 2.2 AA, the default
```

## The keys

| Key | Value | When left out |
|---|---|---|
| `version` | integer from 1 | refused |
| `published_at` | moment | refused |
| `standard` | `wcag-2.0`, `wcag-2.1`, `wcag-2.2` | `wcag-2.2` |
| `level` | `A`, `AA`, `AAA` | `AA` |
| `status` | `full`, `partial`, `non-conformant` | the status the non-conformities support |
| `entity` | string | none |
| `scope` | text | none |
| `feedback` | map: `email`, `phone`, `url`, `postal_address` (text), `response_days` | refused |
| `non_conformities` | list of maps: `criterion` (required, such as `1.4.3`), `reason` (`non-compliance` by default, `disproportionate-burden`, `out-of-scope`), `description`, `alternative` (texts), `fix_by` (moment), `occurrences` (integer), `from_audit` (boolean) | none |
| `alternatives` | list of texts | none |
| `enforcement` | map: `procedure` (text, required), `authority` (text), `url` | none |
| `evaluation` | map: `evaluated_at` (moment, required), `method` (`self-assessment` by default, `third-party`, `automated`), `pages`, `report_url` | none |

A text is a map of language tags to strings: `{"en": "…", "fr": "…"}`. A moment is a date, `2026-11-08`, read at
midnight UTC, or a date and a time with its offset, `2026-11-08T10:00:00-05:00` or `…Z`; a relative moment such as
`tomorrow` and a date that does not exist such as `2026-02-30` are refused.

`toArray()` leaves out what is not given: a null, an empty list, a zero, a false. It writes moments as
`2026-11-08T10:00:00-05:00`.

## What is refused

An unknown key at any level, a value of the wrong type, a value out of its bounds or a statement that contradicts
itself refuses the whole statement with an `InvalidStatement`: no statement is half read, and no warning is raised.
The message names the key, as in `The value of "level" is A, AA or AAA.` or `The feedback has no key "fax".`
