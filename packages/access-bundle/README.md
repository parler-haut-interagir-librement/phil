# phil/access-bundle

**Who sees and who may change each item of a site: visibility, sharing levels, access groups, access that ends on its
own, and "view as this member", decided by Symfony Security voters.**

phil/access-bundle gives a Symfony site one access layer for its items — pages, documents, events, files. Each item has
an **owner**, a **space** — the site, or one of its parts — and a **visibility**: everyone, the members of the space,
its admins, or the owner alone, private by default. It is **shared** with members or access groups at a **sharing
level** — `manage`, `edit`, `comment`, `view` by default, each including the next —, until a date when one is given:
the share then ends on its own at that second. **Access groups** declare their **entry points** — a purchase, an
invitation, an administrator's bulk action, an automation, or every new member — and how long a membership lasts. An
admin of the space may **view the site as a member**, read-only, without taking their session: every start and stop
is logged and dispatched.

Every right and every sharing level is a declared relation of `phil/resource-permissions`: an item's `view` includes
its `comment`, which includes its `edit`, then its `manage`, which its owner holds; a visibility is a tuple on `view`
given to every visitor, to the members of the space or to its admins; a share is a tuple given to a member or to the
members of a group. So a check is `is_granted('access.edit', $page)`, a list of what a member sees is one bounded
listing of the model, and *why* a member sees an item is the chain of tuples that grants it.

`AccessVoter` answers `access.<level>` for any `ResourceReference` or `AccessibleItem`, and `access.view_as`;
`EasyAdminAccessVoter` answers EasyAdmin's `EA_ACCESS_ENTITY` and `EA_EXECUTE_ACTION` for the entities that are
`AccessibleItem`s. A member is always named by a stable identifier, never an email address. The banner of "view as
this member" and the summary of who has access are Twig components drawn with the recipes of the PHIL UX kit.

## Status

| Part | State |
|---|---|
| The access model: spaces, groups and items, one relation per sharing level, extended by tagged contributors | Implemented and tested |
| Visibility per item — everyone, members, admins, owner — private by default, and the narrowest of two for a link | Implemented and tested |
| Sharing levels, shares with members and access groups, shares that end on their own | Implemented and tested |
| Access groups, their entry points and memberships that end on their own; groups as members or admins of a space | Implemented and tested |
| "View as this member": admins only, read-only, logged and dispatched, started and stopped by plain forms with a CSRF token | Implemented and tested |
| `AccessVoter` and `EasyAdminAccessVoter`, with the reasons of each vote | Implemented and tested |
| The Twig functions `access_can`, `access_visibility`, `access_viewing_as`; the components `PhilAccess:ViewAsBanner` and `PhilAccess:AccessSummary` on the recipes of the kit | Implemented and tested |
| Doctrine DBAL store and schema listener, in-memory store, `phil:access:purge` | Implemented and tested |
| Organizations and their roles, from `phil/organizations-bundle` | Planned, once that bundle exists |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the routes and the table. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [The access model](doc/model.md) | The kinds and relations the bundle declares, and how to add to them. |
| [Items, visibility and sharing](doc/items-and-sharing.md) | Owners, spaces, visibilities, sharing levels and shares that end on their own. |
| [Access groups](doc/access-groups.md) | Groups, entry points, memberships, default groups. |
| [View as this member](doc/view-as.md) | Who may, what it shows, the log and events, the banner. |
| [Checking access](doc/checking.md) | The voters, the Twig functions, listing what a member sees, why. |
| [EasyAdmin](doc/easyadmin.md) | The voter on EasyAdmin's permissions, the scoped CRUD controller, the tenant filter. |
| [Rendering](doc/rendering.md) | The banner and the summary, drawn with the recipes of the kit. |
| [Extension points](doc/extension-points.md) | The services to replace, the tags to add to, and when they are stable. |
| [Testing](doc/testing.md) | The bundle's tests, and how to test an application's access. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```php
// config/bundles.php
Phil\AccessBundle\PhilAccessBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_access.yaml
phil_access:
    item_kinds: [page, document]
    groups:
        supporters: { entry_points: [purchase, invitation], duration: P1M }
```

```php
$items->register($page, MemberId::of($author->id), Visibility::Members);
$sharing->share($page, 'edit', MemberId::of($editor->id), new DateTimeImmutable('+7 days'));
```

```twig
{% if is_granted('access.edit', page) %}…{% endif %}
<twig:PhilAccess:ViewAsBanner />
```

## Requirements

PHP 8.5 or later, Symfony 8.2 or later and Doctrine DBAL 4.5 or later; SecurityBundle for the voters, TwigBundle and
Symfony UX Twig Component for the functions and components, EasyAdmin 5.8 or later for its voter.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
