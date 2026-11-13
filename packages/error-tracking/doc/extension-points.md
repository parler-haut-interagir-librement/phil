# Extension points

The library has three ports, all interfaces of `Phil\ErrorTracking\Contract`. Their adapters belong to the
application; `phil/error-tracking-bundle` ships one of each.

## `ErrorStore`

Where the groups and their events are kept.

```php
interface ErrorStore
{
    public function find(string $fingerprint): ?ErrorGroup;

    public function save(ErrorGroup $group, ?ErrorEvent $event = null): void;
}
```

- `find()` answers `null` for a fingerprint never seen.
- `save()` writes the group, created or updated, and keeps the event with it when one is given. A store may keep a
  bounded number of events per group, dropping the oldest. An administration page saves a group without an event when
  it resolves, ignores or reopens it.
- Everything a store receives has gone through the scrubber. `ErrorEvent::toArray()` gives the plain values to write
  and `ErrorEvent::fromArray()` reads them back.
- Two processes may read the same group and save it one after the other, and one count is lost; a store that wants
  exact counts adds to them in its own statement.

## `CaptureThrottle`

Bounds the events of a key — the fingerprint — in a span of time: see
[Sampling and throttling](sampling-and-throttling.md).

## `GroupAlerter`

Hears of a first event and of a regression, with the group as it was saved:

```php
interface GroupAlerter
{
    public function alert(ErrorGroup $group, Occurrence $occurrence): void;
}
```

An alerter that sends a chat message or an e-mail does well to queue it rather than send it in the request that failed.
It names the group by its title, its culprit and its fingerprint; the event itself, even scrubbed, stays in the store.
