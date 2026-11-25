# Changelog

All notable changes to `phil/audit-viewer` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-24

### Changed

- A test proves that an anonymous token is refused `PHIL_AUDIT_VIEW` and `PHIL_AUDIT_HISTORY`, with or without a role of
  the history, with roles any signed-in user holds, under a policy that allows everything and is then never asked, and
  under each strategy of the access decision manager.
- Requires the latest minor of each dependency (`twig/twig` 3.31, `easycorp/easyadmin-bundle` 5.10), and of each
  development requirement (`doctrine/orm` 3.8, `doctrine/dbal` 4.6, `doctrine/doctrine-bundle` 3.4,
  `symfony/ux-twig-component` 3.8, …).

## 0.1.0-alpha - 2026-11-14

### Added

- `AuditViewerController`, the pages of the audit log under every EasyAdmin dashboard: the log with its filters and
  its cursor pages, one row with its payload — its fields when a `PayloadReader` reads its version — and whether it
  matches its hash, the history of one record, the state of the hash chain grouped by site and the verification of a
  segment; every response private and not stored. Each row a page of the log lists is asked for `PHIL_AUDIT_VIEW`
  with its `AuditRow`, and each segment of the chain page with its name, so that an `AuditAccessPolicy` that narrows by
  record or by site holds on the lists too; a row refused is left out.
- The CSV export of the log's filters, at most `export_limit` rows read, each asked for `PHIL_AUDIT_EXPORT` with its
  `AuditRow` and left out when refused, behind a CSRF token, recorded in the trail —
  `audit.export`, the actor, the filters and the limit — before its first line; a cell a spreadsheet would read as a
  formula — it starts with `=`, `+`, `-`, `@`, a tab, a carriage return or a line feed — is written after a `'`.
- `AuditFilters`, the filters of the page read from a query string or a form — the tenant among them —, the period in
  the viewer's time zone, a date-time of the wrong shape refused before it is parsed.
- `AuditHistoryActions`, an EasyAdmin actions extension: a "History" action on the detail page and the index of every
  CRUD controller whose entity implements `Auditable`, shown to whoever is granted `PHIL_AUDIT_HISTORY` on the record.
- `AuditViewerVoter`, which grants `PHIL_AUDIT_VIEW`, `PHIL_AUDIT_HISTORY` and `PHIL_AUDIT_EXPORT` to their roles,
  asked with the row, the record or the segment, and says why; it grants only once the application's
  `AuditAccessPolicy`, when there is one, allows the attribute on its subject, so that it never widens access under
  the `affirmative` strategy; `AuditViewerAdmin::menuItems()`, the links of a dashboard's menu.
- The keys `role`, `export_role`, `history_role`, `access_policy`, `history_action`, `export_segment`,
  `export_limit`, `page_size` and `timezone`; the port `AuditAccessPolicy`.
- The fuzz target `log-filters`.
- The templates, extending EasyAdmin's content page, and the translations of the `phil_audit_viewer` domain in English
  and French.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
