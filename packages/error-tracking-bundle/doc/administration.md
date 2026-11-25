# Administration

The pages are off unless `admin` is enabled, with `admin: true` or any of its keys and `enabled: true`.

## The pages

| Route | Path under the prefix | What it shows |
|---|---|---|
| `phil_error_tracking_groups` | `/` | The groups seen last first, by status (`?status=open`) and by page (`?page=2`) |
| `phil_error_tracking_group` | `/{fingerprint}` | A group, its status and releases, and its latest events with their chains, frames, request and tags |
| `phil_error_tracking_group_status` | `/{fingerprint}/{action}`, `POST` | Resolves, ignores or reopens the group, then shows it again |

Every page answers with `Cache-Control: no-store`. Every value on them has gone through the scrubber. A chain reads
from the top down: the exception thrown, then each previous one marked "Caused by", the root cause last as "Root
cause". The frames of each exception start with the newest; each run of frames under `vendor/` is folded into a
`<details>` element that tells how many it holds and opens without script. When the alerts are on but have no destination — the degraded state of [Alerts](alerts.md) —
both pages say so at the top, in a `role="status"` paragraph of `@PhilErrorTracking/_alerts.html.twig`.

## The role

Every page and action asks for `admin.role`, `ROLE_ADMIN` by default. A user without it meets the firewall as with any
denied page: a login form, or a 403. Import the routes under a prefix the firewall covers.

## The actions

The forms post a CSRF token of the identifier `phil_error_tracking`; a missing or forged token is a 400. Resolving keeps
the last release seen as the release of the resolution: an event of another release regresses the group. Ignoring
keeps counting without alerts. Reopening clears the resolution.

## The layout

The pages extend `admin.layout`, `@PhilErrorTracking/layout.html.twig` by default, a plain page without assets. An
application gives its own layout, which defines the `title` and `main` blocks — the names EasyAdmin's content page
uses, so that the same pages fill a back office:

```yaml
phil_error_tracking:
    admin:
        layout: admin/layout.html.twig
```

In a back office, see below: the layout is EasyAdmin's, whatever `admin.layout` says.

The templates themselves, `@PhilErrorTracking/groups.html.twig`, `@PhilErrorTracking/group.html.twig` and
`@PhilErrorTracking/_alerts.html.twig`, can be overridden under `templates/bundles/PhilErrorTrackingBundle/`.

## In an EasyAdmin back office

With EasyAdminBundle registered, the bundle grafts the same pages on every dashboard through `#[AdminRoute]`, with the
same role, the same refusals and the same forms. EasyAdmin names their routes after the dashboard's:

| Route under the dashboard `admin` | Path under the dashboard's | Same page as |
|---|---|---|
| `admin_errors_groups` | `/errors` | `phil_error_tracking_groups` |
| `admin_errors_group` | `/errors/{fingerprint}` | `phil_error_tracking_group` |
| `admin_errors_group_status` | `/errors/{fingerprint}/{action}`, `POST` | `phil_error_tracking_group_status` |

On these routes the pages render in `@PhilErrorTracking/easyadmin/layout.html.twig`, which extends EasyAdmin's content
page: the page's title becomes the content title, its body the `main` block, inside the dashboard's menu and theme.
Their links and the redirection after a form stay on the routes of the same dashboard. Importing
`config/routes.php` is then optional; the notified alerts link to a group only when the bundle's own routes exist.

The dashboard's menu links to the list:

```php
use Phil\ErrorTrackingBundle\Infrastructure\EasyAdmin\ErrorTrackingAdmin;

public function configureMenuItems(): iterable
{
    yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
    yield ErrorTrackingAdmin::menuItem('admin');
}
```

`ErrorTrackingAdmin::menuItem()` takes the dashboard's route name, a label (`Errors`) and an icon (`fa fa-bug`). Cover
the dashboard's path with the firewall, as for any page of the back office.
