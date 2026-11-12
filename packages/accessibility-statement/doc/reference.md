# Reference

| Type | Role |
|---|---|
| `Statement` | One version of a statement: target, status, feedback, non-conformities, alternatives, scope, enforcement, evaluation, organisation; `blocking()`, `withReason()`, `lastEvaluatedAt()`, `toArray()`. |
| `StatementReader` | Reads a statement from plain data or JSON, every key checked. |
| `StatementGenerator` | Writes the next version from the current one and an audit. |
| `ConformanceTarget` | A version of WCAG and a level; `covers()`, `criteria()`, `describe()`. |
| `Standard` | `Wcag20`, `Wcag21`, `Wcag22`; `title()`, `url()`, `includes()`. |
| `ConformanceStatus` | `Full`, `Partial`, `NonConformant`; `derive()`. |
| `NonConformity` | A success criterion not met, with its reason, description, alternative, date of fix, occurrences and origin; `failedCriteria()`. |
| `NonConformityReason` | `NonCompliance`, `DisproportionateBurden`, `OutOfScope`; `countsAgainstConformance()`. |
| `FeedbackContact` | Email, telephone, page, postal address, days to answer; `phoneUri()`. |
| `Enforcement` | The procedure, the authority and its page. |
| `Evaluation` | The moment, method, pages and report of the last evaluation. |
| `EvaluationMethod` | `SelfAssessment`, `ThirdParty`, `Automated`. |
| `LocalizedText` | A plain text per language tag; `of()`, `in()`, `has()`, `locales()`. |
| `Address` | The check of the addresses a statement links to. |
| `CriterionTitles` | `french()`: the titles of the success criteria in the W3C Authorized Translation of WCAG 2.2 into French, by number. |
| `Audit\AuditResult` | An audit: moment, pages, method, report. |
| `Audit\AuditedPage` | A page of an audit and its `Report`. |
| `Audit\ReportReader` | Reads back the JSON of a report of `phil/accessibility-check`. |
| `Contract\AuditSource` | The port that gives the latest audit. |
| `Exception\AccessibilityStatementException` | What every exception of the library implements. |
| `Exception\InvalidStatement` | A statement, a part or data that cannot be published or read. |
| `Exception\InvalidReport` | A report that cannot be read back. |
