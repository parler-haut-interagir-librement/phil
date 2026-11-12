# Changelog

All notable changes to `phil/accessibility-statement` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-12

### Added

- `Statement`: one version of an accessibility statement, never changed once published — its number, publication,
  `ConformanceTarget`, `ConformanceStatus`, `FeedbackContact`, non-conformities (at most 1 000), alternatives (at most
  50), scope, `Enforcement`, `Evaluation` and organisation —; refused when the status contradicts the
  non-conformities, a criterion lies outside the target or the evaluation follows the publication; `blocking()`,
  `withReason()`, `lastEvaluatedAt()`.
- `ConformanceTarget` and `Standard`: WCAG 2.0, 2.1 and 2.2 at the levels A, AA and AAA of `phil/accessibility-check`,
  the criteria each measures in the order of their numbers, its name and the recommendation of the W3C.
- `ConformanceStatus`: full, partial, non-conformant; `derive()` gives the status the non-conformities support, non-
  conformant beyond half the criteria of the target.
- `NonConformity` and `NonConformityReason`: a success criterion not met, for non-compliance, a disproportionate burden
  or content out of scope — the last not counting against conformance —, with its description, alternative, date of
  fix, occurrences and whether an audit found it; `failedCriteria()`.
- `FeedbackContact`, `Enforcement`, `Evaluation` and `EvaluationMethod`: an email, telephone, page or postal address
  and the days to answer; the procedure, the authority and its page; the moment, method, pages and report of the last
  evaluation.
- `LocalizedText`: a plain text per language tag, up to 32 languages and 10 000 characters, found by locale, primary
  language, fallback, then first; `Address`: an `https://` address or a path of the site, nothing else, the address
  read by `phil/url`: an international host accepted, user information, an IP address, `localhost` and a single name
  refused.
- `StatementReader` and `Statement::toArray()`: a statement as plain data or JSON (1 MiB, 16 levels) and back, every
  key checked, defaults of WCAG 2.2 AA and the derived status, moments as a date or a date and time with its offset.
- `StatementGenerator`: the next version from the current one and an audit — the non-conformities written by hand
  kept, those of the previous audit replaced by one per criterion of the target failed at the minimum severity, with
  their occurrences and what was written of them, the status derived and the evaluation of the audit.
- `Audit\AuditResult`, `Audit\AuditedPage` and `Contract\AuditSource`: the latest audit of the content, its pages and
  their reports, and the port that gives it.
- `Audit\ReportReader`: the JSON reports of `phil/accessibility-check` read back to `Report`s, within 5 MiB, 8 levels
  and 50 000 entries.
- `CriterionTitles::french()` and `data/wcag-2.2-success-criteria.fr.json`: the French titles of the success criteria
  of WCAG 2.2 from the W3C Authorized Translation, under the W3C Document License, credited in `data/NOTICE.md`.
- `Exception\AccessibilityStatementException`, `InvalidStatement` with a reason key, and `InvalidReport`.
- Fuzz targets `statement-reader` and `report-reader`.
