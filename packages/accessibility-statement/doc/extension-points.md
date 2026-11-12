# Extension points

What an application implements, replaces or relies on.

## The latest audit

`Contract\AuditSource::latest(): ?AuditResult` gives the audit the generator reads. Implement it over whatever runs the
checks: a directory of reports read with `ReportReader`, a table of reports, a crawler that renders the pages and calls
`AccessibilityChecker`. An `AuditResult` holds the moment of the audit, its pages — each an `AuditedPage` with the name
the audit gives it and its `Report` —, its `EvaluationMethod` and the address of its own report.

## Storing statements

The library stores nothing. `Statement::toArray()` and `StatementReader` carry a statement to and from any storage
that keeps a map or a JSON document; the version number is its natural key. Keep each version: a statement is never
changed once published.

## Rendering

The library renders nothing. A page shows the parts of a `Statement`: `target->describe()` and `target->standard->url()`
for the claim, `nonConformities` grouped by `reason`, the title of each criterion from `SuccessCriterion::title()` (in
English, the W3C's), the texts in the reader's language with `LocalizedText::in()`, `feedback->phoneUri()` for a
`tel:` link. Every text is plain: escape it.

## The status

An application that judges conformance with its own rule writes the status itself; `Statement` only checks that it
agrees with the non-conformities.
