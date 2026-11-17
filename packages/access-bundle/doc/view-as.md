# View as this member

An admin of the space sees the site as a member sees it, to answer "why can't I see this page?" without asking for
their password or taking their session.

- **Admins only**: the member holds `admin` on the site's space. `is_granted('access.view_as')` says so.
- **Members of that space only**: the member viewed holds `member` on the same space — every admin does too. With
  tenants, the space is the tenant's own, so an admin of one tenant never views a member of another.
- **Read-only**: while the view lasts, every check is the member's, and only the narrowest level — viewing — may be
  granted; a listing at a wider level is empty. The guard answers 403 to a request that would change something. See
  [The boundary](#the-boundary).
- **Logged**: each start, stop and refusal is a notice or a warning of the `phil_access` log channel, by identifiers
  alone; each start and stop dispatches `ViewAsStarted` or `ViewAsStopped`.
- **Kept in the session**, by the member's identifier; removed from it as soon as the admin is no longer one, is no
  longer signed in, or the member leaves the space.

An admin does not view the site as themselves; a visitor, a member who is no admin, or an admin who names someone who
is no member of the space is refused with `ViewAsRefused`.

## The boundary

The view changes who **the checks of this bundle** answer for: `is_granted('access.<level>', $item)`, the EasyAdmin
voter, `access_can()`, `ItemAccess`. The security token still holds the admin, with every role. What does not ask the
bundle — `is_granted('ROLE_ADMIN')`, another voter, a controller or a service of the application — still sees the
admin and grants what the admin may do.

Two safeguards keep the view read-only for the rest of the application:

- **The guard**, `Infrastructure\Http\ViewAsGuard`, on `kernel.controller`. While a view lasts, a request whose method
  is not safe — `POST`, `PUT`, `PATCH`, `DELETE` — is answered with 403, and logged. A controller marked
  `#[AllowedDuringViewAs]` goes through: the forms that start and stop the view are. A controller marked
  `#[RefusedDuringViewAs]` is refused whatever the method: a `GET` that changes something, an export, a download.
  `view_as.read_only: false` removes the guard.

  ```php
  use Phil\AccessBundle\Infrastructure\Http\Attribute\RefusedDuringViewAs;

  #[RefusedDuringViewAs]
  #[Route('/members/export', methods: ['GET'])]
  public function export(): Response
  ```

- **`ViewAs::refuseWhileViewing()`**, for a change that does not come from a controller the guard sees — a command
  handler, a service called from a template, a Live Component action. It throws `ViewAsRefused` while a view lasts.

  ```php
  public function __invoke(RenameItem $command): void
  {
      $this->viewAs->refuseWhileViewing();
      // …
  }
  ```

## The end of a view

A view ends when the admin stops it, and on its own as soon as `ViewAs::member()` finds that the admin is no longer an
admin of the space, is no longer signed in, or that the member left the space. The member viewed is then removed from
the session — getting the right back later does not bring the view back —, a notice is logged, and `ViewAsStopped`
carries the reason: `ViewAsEnd::Stopped`, `ViewAsEnd::AdminRightLost` or `ViewAsEnd::MemberLeftSpace`. Its `admin` is
empty when nobody is signed in any more.

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
The controller carries `#[AllowedDuringViewAs]`, so the guard lets both forms through during a view.

## The banner

`<twig:PhilAccess:ViewAsBanner />`, in the layout, tells the admin who they view the site as, that the view only
reads, and holds the form that stops it. Nothing is drawn otherwise. See [Rendering](rendering.md).

## In code

```php
$viewAs->start(MemberId::of('01JABC'));
$viewAs->member();             // ?MemberId; ends a view whose admin or member no longer qualifies
$viewAs->isAllowed();          // bool
$viewAs->refuseWhileViewing(); // throws ViewAsRefused during a view
$viewAs->stop();
```
