# phil/audit-viewer

**The audit log of `phil/audit-bundle`, read in an EasyAdmin back office.**

phil/audit-viewer adds the pages of the audit log to every EasyAdmin dashboard of a Symfony application: the log with
its filters, one row with its payload and hashes, the history of one record, the state of the hash chain grouped by
site, the verification of a segment, and a CSV export. Every CRUD page of an entity that implements `Auditable` gets
a "History" action that leads to that record's history. The pages call the services of `phil/audit-bundle` — `AuditLog`,
`ChainStatus`, `AuditTrail` — and change nothing in the log: the export is the one thing they write, a row recording
who exported what, written before the first line of the file.

It exists because an audit trail nobody reads proves little: the people who answer for it need to find who did what,
see the history of an order or a page, check that the chain holds, and hand the rows to someone else, without a
console. The bundle keeps that reading apart from the trail itself, which stays a service without screens.

It is for applications on `phil/audit-bundle` with an EasyAdmin back office, for one site or many. Every page asks for
its own attribute, `PHIL_AUDIT_VIEW`, `PHIL_AUDIT_HISTORY` or `PHIL_AUDIT_EXPORT`, granted by default to a dedicated
role, so that the history of a record can be opened to the people who manage it without the whole log. It does not edit
or revert a row, anchor the trail, or decide who may read it beyond those roles.

The bundle is a Symfony bundle on EasyAdmin 5, `phil/audit-bundle`, the Security component and Twig. Its pages are
EasyAdmin pages in the dashboard's layout, with nothing outside it.

## Status

| Part | State |
|---|---|
| `AuditViewerController`: the log, a row, a history, the chain, a segment, the export | Implemented and tested |
| `AuditFilters`, `AuditCsv` | Implemented and tested |
| `AuditHistoryActions`: the "History" action of audited entities | Implemented and tested |
| `AuditViewerVoter`, `AuditViewerAdmin` | Implemented and tested |
| `AuditAccessPolicy` held on every page and list: rows of the log, segments of the chain, rows of the export | Implemented and tested |
| `PhilAuditViewerBundle` | Configuration `role`, `export_role`, `history_role`, `access_policy`, `history_action`, `export_segment`, `export_limit`, `page_size`, `timezone` |

## Documentation

- [Installation](doc/installation.md) — requirements, registering the bundle, and what `phil/audit-bundle` must have
  configured.
- [Configuration](doc/configuration.md) — the nine keys under `phil_audit_viewer` and the services wired.
- [Pages](doc/pages.md) — each page, its route under a dashboard, its filters and what it shows, the chain by site.
- [The history action](doc/history-action.md) — the action on the CRUD pages of audited entities, who sees it, and
  how to turn it off or replace it.
- [Access and export](doc/access-and-export.md) — the three attributes and their roles, the CSV export, how it is
  recorded and what it protects against.
- [Extension points](doc/extension-points.md) — the access policy that narrows the roles, voters of your own, the
  payload reader, the templates and the translations.
- [Testing](doc/testing.md) — how the bundle is tested, its fuzz target, and how to run its tests.
- [API reference](doc/reference.md) — every type, with its role.

## Quick start

```bash
composer require phil/audit-viewer:^0.1@dev
```

```php
use Phil\AuditViewer\Infrastructure\EasyAdmin\AuditViewerAdmin;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function configureMenuItems(): iterable
    {
        yield from AuditViewerAdmin::menuItems('admin');   // admin_audit_log, admin_audit_chain
    }
}
```

```yaml
# config/packages/phil_audit_viewer.yaml
phil_audit_viewer:
    role: ROLE_AUDIT_VIEWER
    export_role: ROLE_AUDIT_EXPORT
    history_role: ROLE_ADMIN
```

The log is then at `/admin/audit`, for the holders of `ROLE_AUDIT_VIEWER`, and the detail page of an order whose
entity implements `Auditable` shows a "History" button to them and to the holders of `ROLE_ADMIN`.

## Requirements

PHP 8.5 or later, `phil/audit-bundle` 0.1 on a Doctrine connection, EasyAdmin 5.10.4, Symfony 8.2 with SecurityBundle,
CSRF protection, the router and the translator, and Twig 3.21.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
