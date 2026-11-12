# Testing

## The library's tests

The suite `tests/Unit` tests the texts and their languages, the addresses, the feedback contact, the enforcement
procedure, the evaluation, the non-conformities, the versions of WCAG and the criteria each target measures, the
status derived, every refusal of a statement, the plain data written and read back with every refusal of the reader,
the reports read back, and the next version generated from an audit.

From the monorepo root:

```sh
vendor/bin/phpunit packages/accessibility-statement/tests
```

The library is held to 100 % covered mutation score by Infection.

## Fuzzing

Two fuzz targets give the readers inputs nobody wrote, from the seeds of `tests/Fuzz/corpus/`:

- `statement-reader`: whatever `StatementReader::fromJson()` reads, it returns a statement that writes back to the data
  it reads again, or refuses the input with an `InvalidStatement`, and never throws anything else nor warns;
- `report-reader`: whatever `ReportReader::fromJson()` reads, it returns a report that serializes to JSON, or refuses
  the input with an `InvalidReport`.

```sh
composer fuzz -- statement-reader report-reader
```

## Testing with the library

A statement is a value: build one in a test, or read one from an array, and compare `toArray()`. A generator test
needs no checker either: an `AuditResult` of `Report`s built from `Finding`s gives the findings wanted.
