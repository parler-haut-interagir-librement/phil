# Access and export

## Three attributes

| Attribute | Asked by, with what | Granted by `AuditViewerVoter` to |
|---|---|---|
| `PHIL_AUDIT_VIEW` | every page; the row page with the `AuditRow` too, the verification with the segment's name; each row the log lists with its `AuditRow`, each segment the chain page lists with its name | the holders of `role`, `ROLE_AUDIT_VIEWER` by default |
| `PHIL_AUDIT_HISTORY` | the history of a record, with its `Subject`; the "History" action, with the record's `Subject` | the holders of `history_role` or of `role` |
| `PHIL_AUDIT_EXPORT` | the export, and its button on the log; each row the export reads, with its `AuditRow` | the holders of `export_role`, the viewer's role when it is `null` |

The voter asks the access decision manager whether the token holds the role, so a role hierarchy applies, and gives
its reason to the vote — `PHIL_AUDIT_HISTORY is granted to ROLE_ADMIN.`, `PHIL_AUDIT_VIEW needs ROLE_AUDIT_VIEWER.` —,
which the profiler's security panel shows. Once the role holds, the voter asks the application's `AuditAccessPolicy`,
when `access_policy` names one, and refuses what it refuses — `PHIL_AUDIT_HISTORY is refused by the access policy to
ROLE_ADMIN.` —; it grants nothing else, so it never widens access, whatever the access decision strategy
([extension-points.md](extension-points.md)). The row page asks for `PHIL_AUDIT_VIEW` before it reads the row, then with
the row: a user without the attribute cannot tell a row that exists from one that does not. A user
without the attribute meets the firewall, as on any denied page: `401` without a session, `403` with one. Reading the
log of personal records is a right of its own: give the role to the people who answer for the trail, not to every
administrator.

In a tenant — with `phil/tenancy-bundle` — the pages read the current tenant's rows only, through `AuditLog`, and the
chain page that site's segments only, through `ChainStatus`, as `doc/reading.md` and `doc/sites.md` of
`phil/audit-bundle` explain. A viewer outside any tenant reads every site, and filters the log on one with `tenant`.

## The CSV export

The log page shows the button to the holders of `PHIL_AUDIT_EXPORT` when `phil/audit-bundle` has an `AuditTrail`. It
posts the page's filters with a CSRF token of id `phil_audit_viewer_export`; a missing or wrong token is a `400`, and
so are filters that do not read.

Before the first line is written, the export is recorded in the trail, in the segment `export_segment`:

- the action `audit.export` and the actor the `ActorResolver` gives — the stable identifier of a user that implements
  `AuditActor` —, or none;
- in the payload, `format`, `limit`, and each filter as `filter_<field>`: what was asked, never a value of the log.

A download cut short is therefore in the trail too, and an export shows in the log like any other event: the next
export reads it. The file is streamed, read 500 rows at a time, and stops after `export_limit` rows read; a row the
access policy refuses is not written, so an export may hold fewer rows than were read. Its columns:

`recorded_at`, `segment`, `sequence`, `kind`, `action`, `actor`, `subject_type`, `subject_id`, `tenant`,
`serialization_version`, `payload`, `previous_hash`, `hash`, `intact`

`intact` is `yes` or `no`, whether the row matches its hash. The response is an attachment named after the instant
(`audit-20261108-110200.csv`), `text/csv` in UTF-8, `nosniff`, private and not stored.

A cell that starts with `=`, `+`, `-`, `@`, a tab, a carriage return or a line feed is written after a `'`: a
spreadsheet would read it as a formula, and a payload written by anyone must not run when the file is opened.
