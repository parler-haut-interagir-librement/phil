# View as this member

An admin of the space sees the site as a member sees it, to answer "why can't I see this page?" without asking for
their password or taking their session.

- **Admins only**: the member holds `admin` on the site's space. `is_granted('access.view_as')` says so.
- **Read-only**: while the view lasts, every check is the member's, and only the narrowest level — viewing — may be
  granted; a listing at a wider level is empty. Nothing is done in the member's name.
- **Logged**: each start and stop is a notice of the `phil_access` log channel with both identifiers, and dispatches
  `ViewAsStarted` or `ViewAsStopped`.
- **Kept in the session**, by the member's identifier; forgotten as soon as the admin is no longer one.

An admin does not view the site as themselves; a visitor or a member who is no admin is refused with
`ViewAsRefused`.

## The forms

The routes of the bundle start and stop the view from plain forms posted with the token `phil_access_view_as`, then go
back to `view_as.redirect`:

```twig
{% if is_granted('access.view_as') %}
<form method="post" action="{{ path('phil_access_view_as_start', {member: member.id}) }}">
    <input type="hidden" name="_token" value="{{ csrf_token('phil_access_view_as') }}">
    <button type="submit">View as this member</button>
</form>
{% endif %}
```

A form without its token is refused with 403, a refused view with 403, an identifier against the rules with 404.

## The banner

`<twig:PhilAccess:ViewAsBanner />`, in the layout, tells the admin who they view the site as, that the view only
reads, and holds the form that stops it. Nothing is drawn otherwise. See [Rendering](rendering.md).

## In code

```php
$viewAs->start(MemberId::of('01JABC'));
$viewAs->member();    // ?MemberId
$viewAs->isAllowed(); // bool
$viewAs->stop();
```
