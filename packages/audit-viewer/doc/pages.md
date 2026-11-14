# Pages

Every page is an EasyAdmin page of each dashboard, in its layout, and asks for `PHIL_AUDIT_VIEW`; the history of a
record asks for `PHIL_AUDIT_HISTORY`, and the export for `PHIL_AUDIT_EXPORT`. EasyAdmin names each route after the
dashboard's: under a dashboard whose route is `admin` at `/admin`, these are the routes.

| Route | Path | Method | Page |
|---|---|---|---|
| `admin_audit_log` | `/admin/audit` | `GET` | The log, the latest first, with its filters |
| `admin_audit_row` | `/admin/audit/row/{segment}/{sequence}` | `GET` | One row |
| `admin_audit_history` | `/admin/audit/history/{type}/{id}` | `GET` | The history of one record, the earliest first |
| `admin_audit_chain` | `/admin/audit/chain` | `GET` | Every segment with its head |
| `admin_audit_verify` | `/admin/audit/chain/{segment}` | `GET` | One segment, verified against its anchors |
| `admin_audit_export` | `/admin/audit/export` | `POST` | The CSV of the log's filters |

`AuditViewerAdmin::menuItems($dashboardRoute)` gives the links to the log and to the chain, labelled in the
`phil_audit_viewer` domain.

Every response is private and not stored (`Cache-Control: private, no-store`): the log is not kept by a browser or a
proxy.

## The log

A table of rows — instant, segment and sequence, action, actor, record, tenant —, each row linked to its page and
each record to its history. The filters are fields of a `GET` form, so a filtered log is a link to share:

| Field | Filter |
|---|---|
| `actor` | One actor's stable identifier |
| `action`, `prefix` | One action, or with `prefix` ticked every action that starts with it, such as `workflow.order.` |
| `subject_type`, `subject_id` | Every record of a type, or one record; an identifier needs its type |
| `segment` | One segment |
| `tenant` | One tenant's rows, for a viewer outside any tenant who reads every site; in a tenant, the log stays held to it whatever this field says |
| `since`, `until` | From a local date and time included to another excluded, in the time zone of `timezone` |

A value that does not read — an e-mail address as the actor, a date in another format, a tenant with a control character
— is said above the form, the field marked, and the page answers `400` without rows. A page holds `page_size` rows; the
link to the next page carries the filters and a cursor, so paging costs the same on the first page and on the
thousandth.

## A row

Its instant to the microsecond, kind, facts, serialization version, previous hash and hash, and its payload: the
fields a `PayloadReader` reads, or the bytes as stored. A row that does not match its hash — changed after it was
written — says so at the top. A row that does not exist, or that belongs to another tenant than the current one, is a
`404`.

## The history of a record

The rows about one record, the earliest first: the changes of state its workflows recorded (`workflow.order.pay`,
with the places it went from and to) beside every other event about it, each with its actor and its fields. A record
type or an identifier that is not one is a `400`; a record nothing was recorded about shows that it has no history.
A holder of `PHIL_AUDIT_HISTORY` without `PHIL_AUDIT_VIEW` reads the history alone: the rows are not links to their
pages, and the link that filters the log on the record is not shown. The "History" action of an audited entity's CRUD
pages leads here ([history-action.md](history-action.md)).

## The chain

Every segment, by name, with its head row and when it was recorded, read on the key without verifying. When the trail
keeps one chain per site (`site_segments` of `phil/audit-bundle`), the segments are grouped: those shared by every
site first, then each site under its tenant, each segment shown by the name the application gave it, its full name in
its title. In a tenant, only that site's segments are listed, and another site's segment is a `404`. Verifying a
segment reads every row of it and compares it with its anchors, as `phil:audit:verify` does, so it is a link of its
own. The segment's page says whether it holds, holds with breaks a recorded discontinuity explains, or does not hold,
and lists each break with the row it points to, marked as an alarm or explained. A segment without rows is a `404`.
