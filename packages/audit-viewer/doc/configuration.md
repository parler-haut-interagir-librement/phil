# Configuration

```yaml
# config/packages/phil_audit_viewer.yaml
phil_audit_viewer:
    role: ROLE_AUDIT_VIEWER          # reads the log
    export_role: ROLE_AUDIT_EXPORT   # exports it; the viewer's role when null
    history_role: ROLE_ADMIN         # reads the history of one record, besides the viewer's role
    access_policy: null              # an AuditAccessPolicy that narrows the roles, record by record
    history_action: true             # a "History" action on the CRUD pages of Auditable entities
    export_segment: audit            # the segment each export is recorded in
    export_limit: 100000             # the most rows one export reads and writes
    page_size: 50                    # the rows of a page of the log or of a history
    timezone: Europe/Paris           # instants shown and filtered in this time zone; PHP's default when null
```

| Key | Default | Effect |
|---|---|---|
| `role` | `ROLE_AUDIT_VIEWER` | The role `AuditViewerVoter` grants `PHIL_AUDIT_VIEW` to; it cannot be empty |
| `export_role` | `null` | The role it grants `PHIL_AUDIT_EXPORT` to; with `null`, the viewer's role |
| `history_role` | `null` | A role it grants `PHIL_AUDIT_HISTORY` to, besides the viewer's role; with `null`, the viewer's role only |
| `access_policy` | `null` | The id of the application's `AuditAccessPolicy`, which the voter asks once a role holds; it narrows the roles, never widens them ([extension-points.md](extension-points.md)) |
| `history_action` | `true` | Whether `AuditHistoryActions` adds the "History" action to the CRUD pages of entities that implement `Auditable` ([history-action.md](history-action.md)) |
| `export_segment` | `audit` | The segment of the trail each export is recorded in: 1 to 64 lower-case letters, digits, `.`, `_` or `-` |
| `export_limit` | `100000` | The most rows one export reads, and so writes, at least 1; a row the access policy refuses is read but not written |
| `page_size` | `50` | The rows of a page, 1 to 500 |
| `timezone` | `null` | The time zone instants are shown and the period filtered in; with `null`, PHP's default time zone when the container is built |

An unknown key or a value out of bounds is refused when the container is built.

## Services wired

| Service id | Public | Definition |
|---|---|---|
| `AuditViewerController` | yes | With Twig, `AuditLog`, `ChainStatus`, the authorization checker, the CSRF token manager, the clock, the keys above, and `AuditTrail`, `ActorResolver` and `PayloadReader` when there are; tagged as an EasyAdmin admin route controller |
| `AuditViewerVoter` | no | With the access decision manager, the viewer's role, the export role, `history_role` and the `access_policy` service; tagged `security.voter` |
| `AuditHistoryActions` | no | With the router and the authorization checker, when `history_action` is on; tagged `ea.actions_extension` |
