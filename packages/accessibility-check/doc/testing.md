# Testing

## The library's tests

The suite `tests/Unit` tests each rule on HTML and on UJO documents — what it reports, where, with which severity, and
what it leaves alone —, the colours of CSS and the contrast of WCAG, the language tags, the reading of HTML and of UJO
documents at their limits, the accessible names, the policy, the messages in their six languages, the report and its
JSON, the constraint, the publication guard and the command.

From the monorepo root:

```sh
vendor/bin/phpunit packages/accessibility-check/tests
```

The library is held to 100 % covered mutation score by Infection; the mutants no test can tell apart are named, with
their reason, in `phil-dev.php`.

## Fuzzing

The fuzz target `html-checker` gives the HTML checker inputs nobody wrote, from the seeds of `tests/Fuzz/corpus/`:
whatever it reads, it returns a report that serializes to JSON, or refuses the content with one of its own exceptions,
and never throws anything else nor warns. From the monorepo root:

```sh
composer fuzz -- html-checker
```

## Benchmark

`tests/Benchmark/AccessibilityCheckBench.php` checks a document of 50 pages — 1 500 paragraphs, 300 headings,
100 images, 50 tables, some 600 links and coloured asides — as HTML and as a UJO document:

```sh
composer bench -- packages/accessibility-check/tests/Benchmark
```

The check reads the content once to index it, then each rule reads the index: the time grows with the size of the
content, not with its square.

## Testing code that checks content

The report is deterministic. Compare the rules and keys of its findings, or their locations:

```php
$report = new AccessibilityChecker()->checkHtml('<img src="cat.jpg">');

self::assertFalse($report->passes());
self::assertSame(
    [['image-alt', 'image-alt.missing']],
    array_map(static fn (Finding $finding): array => [$finding->rule, $finding->message->key], $report->findings),
);
```

A rule of the application is tested on a content that breaks it and one that does not, through `HtmlTarget::parse()`
or `UjoTarget::read()` and its `checkHtml()` or `checkUjo()`. A form that validates with `AccessibleContent` is tested
with Symfony's `ConstraintValidatorTestCase` and `AccessibleContentValidator`, as the library's own test does; a guard,
with a `GuardEvent` built by hand.
