# Generating from an audit

`StatementGenerator` writes the next version of a statement from the latest audit of the content, the reports
`phil/accessibility-check` wrote for its pages.

```php
use Phil\AccessibilityCheck\AccessibilityChecker;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityStatement\Audit\AuditedPage;
use Phil\AccessibilityStatement\Audit\AuditResult;
use Phil\AccessibilityStatement\EvaluationMethod;
use Phil\AccessibilityStatement\StatementGenerator;

$checker = new AccessibilityChecker();
$audit = new AuditResult(new DateTimeImmutable(), [
    new AuditedPage('/', $checker->checkHtml($home)),
    new AuditedPage('/events', $checker->checkHtml($events)),
], EvaluationMethod::Automated, reportUrl: '/accessibility/audit.html');

$next = new StatementGenerator(Severity::Error)->generate($current, $audit, new DateTimeImmutable());
```

## What the next version holds

- Everything an audit does not know comes from the current version: the target, the feedback contact, the
  alternatives, the scope, the enforcement procedure, the organisation.
- The non-conformities written by hand (`fromAudit` false) are kept as they are, first.
- The non-conformities of the previous audit are replaced by those of this one: one for each success criterion of the
  target that a finding of the minimum severity or graver fails — `Error` by default —, in the order of the criteria,
  with the number of findings across the pages as `occurrences`, and `fromAudit` true.
- A criterion the previous audit found already keeps what was written of it: its reason, description, alternative and
  date of fix. A criterion the audit no longer finds is lifted.
- A criterion that a non-conformity written by hand names is left to it. A criterion outside the target — a finding
  of a level AAA criterion under an AA target — is not a non-conformity of the statement.
- The status is the one the non-conformities support (`ConformanceStatus::derive()`).
- The evaluation is the audit's: its moment, its method, the number of its pages and its report.
- The version is one more than the current one, unless `generate()` is given another number.

An automated check does not see every barrier: a statement generated from one says `EvaluationMethod::Automated`, and
the non-conformities people found stay in it, written by hand.

## Reading reports back

An audit run elsewhere — by `phil:accessibility:check --format=json`, or by an application that stores
`json_encode($report)` — is read back by `Audit\ReportReader`:

```php
use Phil\AccessibilityStatement\Audit\ReportReader;

$report = new ReportReader()->fromJson(file_get_contents('var/accessibility/home.json'));
```

It reads the outcome of each rule and each finding: its rule, criterion, severity, message key and parameters, and
location. The text of a message is written again from the catalogue of `phil/accessibility-check`, in the locale of the
report. A report larger than 5 MiB (or the size given to the constructor), deeper than a report is, or not shaped as
one, is refused with an `InvalidReport`; at most 50 000 rules and 50 000 findings are read.

## Where the audit comes from

`Contract\AuditSource` is the port an application implements to give the latest audit: a directory of reports, a
table, a service that checks the pages of the site. `latest()` returns an `AuditResult`, or `null` when there is none
yet. `phil/accessibility-statement-bundle` gives one that reads a directory of JSON reports.
