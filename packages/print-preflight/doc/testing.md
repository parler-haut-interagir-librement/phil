# Testing

## The library's tests

The suite `tests/Unit` builds its documents from the model, with no file: the units and their conversions, the
tolerance at its exact limits, each validation and its message, every rule of every check with the exact sentence,
severity, page, subject and parameters it reports, the limits of the placement rules on each edge to a tenth of a
point, the profile's severities and ignored rules, the order of the report and a check added by the application.

From the monorepo root:

```sh
vendor/bin/phpunit packages/print-preflight/tests
```

The library is held to 100 % covered mutation score by Infection.

## Testing code that runs a preflight

Build the model directly, with round numbers in points, and compare codes rather than sentences when the test is about
which rules fire:

```php
use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Finding;
use Phil\PrintPreflight\Page;
use Phil\PrintPreflight\Preflight;
use Phil\PrintPreflight\Size;

$report = new Preflight()->check(new Document([new Page(Size::pt(500, 700))]));

self::assertSame(['bleed.too-small'], array_map(static fn (Finding $finding): string => $finding->code, $report->findings));
```

The preflight is deterministic: the same document and profile give the same report, in the same order.
