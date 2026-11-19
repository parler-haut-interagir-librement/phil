# Pages

## The routes

| Route | Path | Page | Who |
|---|---|---|---|
| `phil_library_catalogue` | `/` | The search and a page of titles | Everyone |
| `phil_library_title` | `/titles/{id}` | A title's record and its copies | Everyone |
| `phil_library_my_loans` | `/my-loans` | The member's loans | The member the resolver names |
| `phil_library_desk` | `/desk` | Lend a copy, take one back | `roles.desk` |
| `phil_library_admin_title_new` | `/admin/titles/new` | A new title | `roles.admin` |
| `phil_library_admin_title_edit` | `/admin/titles/{id}` | A title's record | `roles.admin` |
| `phil_library_admin_copy_new` | `/admin/titles/{id}/copies/new` | A new copy of a title | `roles.admin` |
| `phil_library_admin_overdue` | `/admin/overdue` | The loans overdue, with their members | `roles.admin` |

The paths are under the prefix the application mounts them at. A staff page asks the application's authorization
checker for its role; without security, it is refused to everyone. The member's page asks the application's
`MemberResolver`, named by `member_resolver`: a request it names nobody for is refused, and without a resolver the page
is not found. A title that does not exist is not found. The pages are sent `private, no-cache`.

## Without JavaScript

The search is a form sent by GET: a page of results is a link, the pages follow by `rel="prev"` and `rel="next"`
links, and nothing needs a script. It carries no CSRF token, since it changes nothing. The desk and the administration
are forms sent by POST with Symfony's CSRF token; a refusal is said on the form, in the reader's language, and answered
422. A title saved is followed by a `303 See Other` to its page.

## The recipes of the kit

The templates draw their parts with recipes of the PHIL UX kit, installed in the application by the Symfony UX
Toolkit; nothing is rendered from `vendor/`:

| Template | Recipes |
|---|---|
| `catalogue.html.twig` | `Button` to search and to page; `Table` with its parts for the titles; `Badge` for the copies on the shelf |
| `title.html.twig` | `Card` with `Card:Header`, `Card:Title`, `Card:Description` and `Card:Content` for the record; `Table` for the copies |
| `_availability.html.twig` | `Badge`: on the shelf, out until a date, overdue in red, withdrawn |
| `loans.html.twig`, `admin/overdue.html.twig`, `_loans.html.twig` | `Table` for the loans; `Badge` for where each stands |
| `desk.html.twig` | `Alert` with `Alert:Description` for the loan lent or returned; `Button` to send each form |
| `admin/title_form.html.twig`, `admin/copy_form.html.twig` | `Button` to save and to add a copy |

These recipes have the names, props and blocks of the Shadcn kit of the Toolkit, so a site on Tailwind installs them
from the Shadcn kit instead. They are found under the components subdirectory of the `ux_toolkit` configuration, as
`phil_library_kit('Table:Row')` names them; where the Toolkit is not enabled, as production often is, repeat it in
`pages.kit_subdirectory`. The layout of the bundle is a bare HTML page; `pages.layout` names the application's own,
which defines `title` and `content` and loads the stylesheet of phil/ux-css.

## Templates

| Template | Context |
|---|---|
| `@PhilLibrary/catalogue.html.twig` | `form`, `result`, `query`, `layout` |
| `@PhilLibrary/title.html.twig` | `title`, `record`, `copies` (each `copy` and the loan `out` that keeps it now), `now`, `layout` |
| `@PhilLibrary/loans.html.twig` | `loans`, `now`, `layout` |
| `@PhilLibrary/desk.html.twig` | `lend`, `return`, `done` (the loan lent or returned by the request), `layout` |
| `@PhilLibrary/admin/title_form.html.twig` | `form`, `id` (null for a new title), `layout` |
| `@PhilLibrary/admin/copy_form.html.twig` | `form`, `title`, `layout` |
| `@PhilLibrary/admin/overdue.html.twig` | `loans`, `now`, `layout` |

An application overrides any of them under `templates/bundles/PhilLibraryBundle/`. The texts are in the
`phil_library` domain, in English and French; the messages of the form constraints in `validators`.
`phil_library_timezone()` gives the library's zone, in which the templates show the dates kept in UTC.

## An EasyAdmin back office

The administration keeps its own routes, forms and layout; an EasyAdmin dashboard links to it:

```php
use Phil\LibraryBundle\Infrastructure\EasyAdmin\LibraryAdmin;

public function configureMenuItems(): iterable
{
    yield from LibraryAdmin::menuItems();    // the desk, the catalogue, a new title, the loans overdue
}
```

Point `pages.layout` to a template of the back office for the pages to look like it.
