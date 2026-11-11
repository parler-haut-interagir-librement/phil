# The admin screens

Server-rendered HTML screens to list, inspect, toggle, create and edit flags. Enabled by `admin`, which needs
`toggle` ([configuration.md](configuration.md#admin)); creation and editing also need a `definition_store` (the
database store gives one).

## Routes

Import them under a prefix ([installation.md](installation.md#the-admin-routes)):

```yaml
phil_feature_flags_admin:
    resource: '@PhilFeatureFlagsBundle/config/routes/admin.php'
    prefix: /admin/flags
```

| Route name | Path | Methods | Action |
|---|---|---|---|
| `phil_feature_flags_admin_list` | `/` | GET | Every flag of the enumerable providers, unreadable ones included. |
| `phil_feature_flags_admin_create` | `/_new` | GET, POST | The creation form (404 without a definition store). |
| `phil_feature_flags_admin_show` | `/{name}` | GET | One flag, with its toggle. |
| `phil_feature_flags_admin_toggle` | `/{name}/toggle` | POST | Toggles the flag. |
| `phil_feature_flags_admin_edit` | `/{name}/edit` | GET, POST | The edit form of a flag of the definition store. |

`{name}` must match the flag-name alphabet (`[a-z0-9]+(?:[._-][a-z0-9]+)*`). The creation form sits at `/_new`,
which no flag name can be, so every flag — `new` included — has its own page.

## Screens

- **List** — each flag with its effective state (`(stored)` when the state store holds it), kind, targeting
  (`everyone`, `N rules`, or the experiment's `A/B by …`), owner, expiry (marked **expired**) and source (the
  provider's service id). A "New flag" link appears when the editor exists. After them, each flag whose stored
  definition no longer reads (`FlagCatalog::unreadable()`) has its own row: its name, **unreadable**, the reason,
  and the source.
- **Unreadable flag** — the page of such a flag: its name, the reason its provider gives, the source, and a
  reminder that it is off for every context (`PARSE_ERROR`); no toggle and no edit link. Answered 200.
- **Flag** — description, state ("as declared by" the provider, or "stored; declared active/inactive by" it), kind,
  owner, expiry, each rule in words (`tenant in tenant-a, tenant-b`, `20 % of each tenant`), the experiment, and a
  form to activate or deactivate with a mandatory reason. A flag whose source is the definition store has an
  "Edit the rules and metadata" link. After a change, a status line says the flag was `toggled`, `created` or
  `edited`.
- **New flag / Edit** — the name (creation only), description, owner, kind (a select), expiry (a date field), the
  rules and experiment as a JSON object, and a mandatory reason. The JSON accepts only the keys `rules` and
  `experiment`; the state cannot be set here: **a new flag starts inactive** (see the exception in
  [toggles-and-edits.md](toggles-and-edits.md#creating-a-flag)) and activating it is a toggle. The edit form
  carries the revision it was read at.

After a successful change the browser is redirected to the flag's page with **303 See Other** and a `done`
query parameter, `toggled`, `created` or `edited`, which the page reports in a status line. Any other value of
`done` is ignored: a crafted link cannot put its own words on the admin screen.

## Responses

| Situation | Status |
|---|---|
| `ActorProvider::current()` returns `null` | 403, on every screen |
| A form posted from another site | 403 |
| Unknown flag, invalid name, creation or edit without the editor, edit of a flag not in the definition store, toggle or edit of an unreadable stored definition | 404 |
| Blank reason, malformed JSON or definition (`InvalidValue`, `JsonException`) | 422, the form shown again with the message |
| Authorizer refused (`ToggleRefused`) | 403, the page or form shown again with the message |
| Concurrent change (`ToggleConflict`, `EditConflict`), name already declared | 409, the page or form shown again with the message |

## Same-origin guard

Every POST (toggle, create, edit) goes through `SameOriginGuard::assert()`, with the standard request headers and
no token:

1. when the browser sends `Sec-Fetch-Site`, it must be `same-origin`;
2. otherwise the `Origin` header must equal the request's scheme and host (`Request::getSchemeAndHttpHost()`);
3. a request carrying neither header is refused.

Behind a reverse proxy, configure Symfony's trusted proxies so that the scheme and host the application sees are
the public ones, or the `Origin` comparison fails. GET screens are not guarded; they change nothing.

## The operator and the firewall

The bundle does not authenticate. The application:

- puts the prefix behind its firewall (an `access_control` rule, a role);
- implements `ActorProvider::current(): ?string`, usually from the security token, returning the opaque identifier
  that goes into audit lines, or `null` when nobody is authenticated;
- decides each change in its `ToggleAuthorizer`.

```php
use Phil\FeatureFlagsBundle\Application\Admin\ActorProvider;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class OperatorProvider implements ActorProvider
{
    public function __construct(private Security $security) {}

    public function current(): ?string
    {
        return $this->security->getUser()?->getUserIdentifier();
    }
}
```

## Templates and layout

The templates live in the bundle's `templates/admin/` and are rendered as `@PhilFeatureFlags/admin/<name>.html.twig`:

| Template | Variables |
|---|---|
| `list.html.twig` | `entries` (`list<CatalogEntry>`), `unreadable` (`list<UnreadableEntry>`), `can_create` (bool) |
| `show.html.twig` | `entry` (`CatalogEntry`), `effective` (`FlagDefinition`), `expired` (bool), `editable` (bool), `done` (string) |
| `unreadable.html.twig` | `entry` (`UnreadableEntry`) |
| `form.html.twig` | `form` (map of `name`, `definition`, `description`, `owner`, `kind`, `expires_on`, all strings), `revision` (`?int`, `null` when creating) |

Every template also receives `layout` (the configured layout), `now` (the clock's time) and `error` (`?string`).

Each template `{% extends layout %}` and fills two blocks, `title` and `content`. The default layout,
`@PhilFeatureFlags/admin/layout.html.twig`, is a bare HTML page. To render the screens inside the application's
back office, point `admin.layout` at a template of the application that defines those two blocks:

```yaml
phil_feature_flags:
    admin:
        layout: 'admin/flags_layout.html.twig'
```

```twig
{# templates/admin/flags_layout.html.twig #}
{% extends 'admin/base.html.twig' %}
{% block title %}{% endblock %}
{% block body %}<div class="flags">{% block content %}{% endblock %}</div>{% endblock %}
```

To change a screen itself, override it the usual Symfony way, under
`templates/bundles/PhilFeatureFlagsBundle/admin/<name>.html.twig`, keeping the form field names (`active`,
`reason`, `name`, `description`, `owner`, `kind`, `expires_on`, `definition`, `revision`) and the route names.

The forms carry no CSRF token: the same-origin guard replaces it.
