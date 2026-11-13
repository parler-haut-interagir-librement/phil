# Testing

## The bundle's tests

From the monorepo root:

```sh
vendor/bin/phpunit packages/print-preflight-bundle/tests
```

`tests/Unit` reads measured documents and their refusals field by field, draws through `PdfLayout` with small PNG and
TrueType files written byte by byte, places media built in memory, and runs the command with `CommandTester`.
`tests/Functional` boots an application with FrameworkBundle, `phil/fonts-bundle` on a font of the tests, a profile and
a check of its own, and checks a measured document whose font is found by family. The bundle is held to 100 % covered
mutation score by Infection.

## Testing an application that uses it

Keep a measured document of a known layout as a JSON fixture and assert on the codes of the report:

```php
$report = $preflighter->check($measuredDocument->fromJson(file_get_contents(__DIR__.'/fixtures/zine.json')), 'booklet');

self::assertSame([], array_map(static fn (Finding $finding): string => $finding->code, $report->errors()));
```
