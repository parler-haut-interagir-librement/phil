# Items, visibility and sharing

## Items

`Application\Items` gives an item — a `ResourceReference` or an `AccessibleItem` — its owner, its space and its
visibility:

```php
$items->register($page, MemberId::of('01JABC'));                          // private to its owner
$items->register($page, MemberId::of('01JABC'), Visibility::Members);      // in the site's space
$items->register($page, MemberId::of('01JABC'), Visibility::Members, 'club'); // in another space
$items->changeVisibility($page, Visibility::Everyone);
$items->transfer($page, MemberId::of('01JXYZ'));
$items->visibility($page); // ?Visibility
$items->owner($page);      // ?MemberId
$items->forget($page);     // every tuple of a deleted item
```

Registering again replaces the owner, the space and the visibility.

## Visibility

| Visibility | Who views the item, besides its owner and its shares | Tuple on `view` |
|---|---|---|
| `Everyone` | every member and every visitor | `member:*`, `visitor:*` |
| `Members` | the members of the item's space, and its admins | `space:<id>#member` |
| `Admins` | the admins of the item's space | `space:<id>#admin` |
| `Owner` | nobody else | none |

The owner always views their item: they hold its widest level. `Owner` is the default: a site that wants items public
from the start says so in `default_visibility`.

A link between two items — a map pin on a page, an entry of a timeline — shows only what both ends allow:
`$a->linking($b)` gives the narrowest of two visibilities.

## Sharing

`Application\Sharing` gives a member or an access group a sharing level on an item:

```php
$sharing->share($page, 'edit', MemberId::of('01JABC'));
$sharing->share($page, 'view', $groups->group('supporters'));
$sharing->share($page, 'comment', MemberId::of('01JABC'), new DateTimeImmutable('2026-12-01 00:00 UTC'));
$sharing->unshare($page, 'edit', MemberId::of('01JABC'));
$sharing->shares($page); // list<Share>: level, member or group, end
```

A share at a level gives every narrower level. Sharing again at the same level replaces the end. A group must be
declared in the configuration.

## Access that ends on its own

A share with an end, and a membership of a group with a duration, stop counting at that second, by Symfony's clock:
no check, listing or summary reads them after it, without a job. An end is after now, or refused. `phil:access:purge`
deletes the expired tuples later.

## Spaces

`Application\Spaces` names the members and admins of the site's space, or of another:

```php
$spaces->addAdmin(MemberId::of('01JADA'));
$spaces->addMember($groups->group('supporters'));  // every supporter is a member of the space
$spaces->isAdmin(MemberId::of('01JADA'), 'club');
```
