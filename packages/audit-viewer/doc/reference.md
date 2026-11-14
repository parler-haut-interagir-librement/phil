# API reference

Every type under `Phil\AuditViewer\`, by layer.

## Bundle

| Type | Role |
|---|---|
| `PhilAuditViewerBundle` | `AbstractBundle`, alias `phil_audit_viewer` ([configuration.md](configuration.md)) |

## Infrastructure

| Type | Role |
|---|---|
| `EasyAdmin\AuditViewerController` | The pages: `log()`, `row()`, `history()`, `chain()`, `verify()`, `export()`; `CSRF_ID` |
| `EasyAdmin\AuditViewerAdmin` | `menuItems($dashboardRoute)`: the links to the log and the chain |
| `EasyAdmin\AuditHistoryActions` | EasyAdmin's `ActionsExtensionInterface`: `supports()`, `extend()`, `action($route)`; `NAME = 'phil_audit_history'`, `PAGES` |
| `Http\AuditFilters` | The filters read from a query string or a form: `read()`, `values`, `query`, `errors`, `parameters()`, `journal()`; `FIELDS` |
| `Csv\AuditCsv` | `write($stream, $rows, $limit)`, `cell()`; `HEADER` |
| `Security\AuditViewerVoter` | Grants `VIEW` (`PHIL_AUDIT_VIEW`), `HISTORY` (`PHIL_AUDIT_HISTORY`) and `EXPORT` (`PHIL_AUDIT_EXPORT`) to their roles when the access policy allows, with the reason; refuses anything else |
| `Security\Contract\AuditAccessPolicy` | Port: `allows($attribute, $subject, $token)`, what the application refuses to a holder of the roles |

## Errors

A filter that does not read is reported by `AuditFilters` rather than thrown. The controller answers with Symfony's
`AccessDeniedException` without the attribute, `NotFoundHttpException` for a row or a segment that does not exist and
for the export without a trail, and `BadRequestHttpException` for a history, a cursor or an export that does not read.
