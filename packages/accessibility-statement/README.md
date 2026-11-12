# phil/accessibility-statement

**Say how accessible a site is, what still keeps readers out, and how to reach you — one dated version at a time.**

phil/accessibility-statement models an accessibility statement: the version of WCAG and the level it measures the
content against, such as WCAG 2.2 AA; the conformance status claimed — full, partial or non-conformant —; the success
criteria not met, each with its reason, what it means for a reader, the alternative offered meanwhile and the date it
will be met; the alternatives that do not depend on the content being accessible; the feedback contact; the
enforcement procedure; and the last evaluation. A statement is published as a version that never changes, and checks
itself: a status the non-conformities contradict, a criterion outside the target or an evaluation dated after the
publication is refused.

It is for any organisation that publishes an accessibility statement and wants it kept true: a statement is written
by hand, read from a configuration or a table, and its next version generated from the reports
`phil/accessibility-check` writes for the pages of the site — the criteria that fail, how often, and when the audit
ran —, while what people wrote of each criterion is kept.

It is a plain PHP library built on `phil/accessibility-check` and the `mbstring` extension.
`phil/accessibility-statement-bundle` gives Symfony applications the page, its versions, its wording in English and
French, and the command that regenerates it.

## Status

| Part | State |
|---|---|
| `Statement`, its target, status, non-conformities, alternatives, feedback, enforcement and evaluation, checked | Implemented and tested |
| WCAG 2.0, 2.1 and 2.2 at levels A, AA and AAA, with the criteria each measures | Implemented and tested |
| The status the non-conformities support | Implemented and tested |
| `StatementReader` and `toArray()`: plain data and JSON, every key checked | Implemented and tested |
| `StatementGenerator`: the next version from an audit | Implemented and tested |
| `ReportReader`: the JSON reports of `phil/accessibility-check` read back | Implemented and tested |
| Fuzz targets of both readers | Implemented and tested |
| The French titles of the success criteria, from the W3C Authorized Translation (see `data/NOTICE.md`) | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Statements](doc/statements.md) | The parts of a statement, the target, non-conformities, what is refused, the status derived, texts and addresses. |
| [Plain data](doc/data.md) | `toArray()`, `StatementReader`, the keys, moments and texts, what is refused. |
| [Generating from an audit](doc/generating.md) | `StatementGenerator`, what the next version holds, `ReportReader`, `AuditSource`. |
| [Extension points](doc/extension-points.md) | The latest audit, storing statements, rendering, the status. |
| [Exceptions](doc/exceptions.md) | `InvalidStatement` and its reasons, `InvalidReport`. |
| [Testing](doc/testing.md) | The library's tests, mutation bar and fuzzing, and how to test with it. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/accessibility-statement
```

```php
use Phil\AccessibilityCheck\AccessibilityChecker;
use Phil\AccessibilityStatement\Audit\AuditedPage;
use Phil\AccessibilityStatement\Audit\AuditResult;
use Phil\AccessibilityStatement\StatementGenerator;
use Phil\AccessibilityStatement\StatementReader;

$current = StatementReader::fromArray([
    'version' => 1,
    'published_at' => '2026-11-01',
    'entity' => 'Example Org',
    'feedback' => ['email' => 'accessibility@example.com', 'response_days' => 10],
]);

$audit = new AuditResult(new DateTimeImmutable(), [
    new AuditedPage('/', new AccessibilityChecker()->checkHtml('<img src="/team.jpg"><p style="color: #aaa">Closed on Mondays.</p>')),
]);

$next = new StatementGenerator()->generate($current, $audit, new DateTimeImmutable());

$next->version;                         // 2
$next->status->value;                   // "partial"
$next->nonConformities[0]->criterion;   // SuccessCriterion::NonTextContent, found once
echo json_encode($next->toArray());     // the data to keep
```

## Requirements

PHP 8.5 or later, with the `mbstring` extension; `phil/accessibility-check` and `phil/url`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
