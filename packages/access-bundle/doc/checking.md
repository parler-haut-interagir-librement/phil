# Checking access

Every check is for the request's subject: the member an admin views the site as, else the signed-in member, else a
visitor, `visitor:anonymous`, whom only what is given to every visitor reaches.

## Voters

`AccessVoter` answers `access.<level>` on a `ResourceReference` or an `AccessibleItem`:

```php
$this->denyAccessUnlessGranted('access.edit', $page);
```

```twig
{% if is_granted('access.manage', page) %}…{% endif %}
```

```php
#[IsGranted('access.view', subject: 'page')]
public function show(Page $page): Response
```

An entity becomes an `AccessibleItem` by naming the reference its tuples are on:

```php
final class Page implements AccessibleItem
{
    public function accessReference(): ResourceReference
    {
        return ResourceReference::of('page', (string) $this->id);
    }
}
```

`access.view_as` is granted to an admin of the space. Each vote carries its reason: the relation given, or not. A
check past the budget is refused and logged as a warning of the `phil_access` channel.

## In templates

| Function | Gives |
|---|---|
| `access_can('edit', page)` | Whether the request's subject may act at that level; the item a `ResourceReference`, an `AccessibleItem` or its text, `page:42`. |
| `access_visibility(page)` | `everyone`, `members`, `admins`, `owner`, or null before it is registered. |
| `access_viewing_as()` | The identifier of the member viewed, or null. |

## In services

`Application\ItemAccess`:

```php
$access->can('edit', $page);                                     // the request's subject
$access->allows(Subject::of($member->reference()), 'view', $page); // another subject
$access->explain('view', $page)?->lines();                       // why
$access->visible('page', limit: 100)->ids();                      // what it views, for WHERE id IN (:ids)
```

`visible()` lists the items of a kind the subject may act on at a level — viewing by default —, ordered by identifier
and limited; `complete` is false when the list was cut. A long list of items is paged by the application's own query
on those identifiers, or by a narrower kind.

## Members

A member is a `MemberId`: 1 to 128 letters, digits, `_`, `.` or `-`, never with `@`. With SecurityBundle, the bundle
reads it from the user identifier of the token, and refuses an identifier that is an email address: such a site names a
service implementing `Application\Contract\CurrentMember` in `phil_access.current_member`, returning the user's stable
identifier.
