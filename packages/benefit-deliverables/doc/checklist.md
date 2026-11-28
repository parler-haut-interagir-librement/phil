# The checklist

## Periods, benefits and entitlements

- **`Period`** names a period the way the application counts them: `2026-11` for a month, `2026-Q4` for a quarter,
  `2026` for a year. Periods are compared by name only.
- **`Benefit`** is something owed each period: a code (`postcard`), a label a person reads, and whether it **ships an
  object**. The code is written in the shipping export, so it names the object, never the tier.
- **`Entitlement`** is what one member is owed for a period: their identifier and the benefits of their tier. The tier
  stays with the source.

A member is named by the stable identifier the application gives them, never an email address, which would change
and would end up in the files: an identifier with `@` is refused.

## Where the entitlements come from

The application implements `Contract\EntitlementSource` over the model of its tiers: subscriptions, memberships,
donations. It answers two questions:

- `entitlements($period, $after, $limit)`: a page of members owed something for the period, ordered by member, after
  the member `$after`;
- `entitlementsOf($period, $members)`: the entitlements of the members named, those owed something only.

A member whose subscription lapsed, or whose donation does not cover the period, is left out by the source: the
checklist owes them nothing.

## The lines

```php
$page = $checklist->page($period);                   // every line of the first 100 members
$page = $checklist->page($period, 'postcard');       // the postcards only
$page = $checklist->remaining($period, 'postcard');  // the postcards not delivered yet
$next = $checklist->remaining($period, 'postcard', $page->next);
```

A page covers up to `$limit` members, 1 to 1000, and gives one `ChecklistItem` per benefit each member is owed: the
`DeliverableKey` (period, benefit, member), the `Benefit`, and the `Delivery` once marked. `$page->next` is the member
the next page starts after, or `null` on the last page. Each page reads the source once and the store once, so a
period of any size is read in bounded memory.

## Marking done

```php
$checklist->markDone($period, 'postcard', ['member-1', 'member-2'], $now, 'staff-7');  // by lot, up to 1000
$checklist->markDone($period, 'credits', ['member-3'], $now, 'staff-7');               // one by one
$checklist->unmark($period, 'postcard', 'member-2');                                   // marked by mistake
```

- Each `Delivery` records when and by whom on the team.
- A member who is not owed the benefit for the period is left out; `markDone()` returns how many were marked.
- A deliverable already done keeps its first delivery: marking it again changes nothing, so a lot can be sent twice.

## Where the deliveries are kept

The application implements `Contract\DeliverableStore`: `deliveries()` reads those of a page of members,
`markDone()` writes a lot and keeps the first delivery of each deliverable, `unmark()` forgets one. A table keyed by
period, benefit and member is enough.
