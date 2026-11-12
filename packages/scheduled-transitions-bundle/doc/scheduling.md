# Scheduling

A transition is a name — `publish`, `unpublish`, `archive`: lower-case letters, digits and `_` — applied to a
resource, known by its kind and identifier, at a moment.

| Method | What it does |
|---|---|
| `schedule($resource, $transition, $at)` | Plans it; the same transition already pending for the resource is moved instead |
| `cancel($resource, $transition)` | Cancels one pending transition, or all of the resource's; returns how many |
| `pendingFor($resource)` | Its pending transitions, soonest first |
| `runDue($limit)` | Applies those due, oldest first, at most so many; a `RunReport` of the applied and the failed |

```php
$page = ResourceReference::of('page', $page->id);
$scheduler->schedule($page, 'publish', new DateTimeImmutable('2026-11-09 09:00', new DateTimeZone('America/Toronto')));
$scheduler->schedule($page, 'unpublish', new DateTimeImmutable('2026-12-09 09:00', new DateTimeZone('America/Toronto')));
```

## Attempts

A handler that throws is a failed attempt: its message is kept in `lastError()` and the transition stays pending, to
be tried at the next run, until `max_attempts` is reached — then it is `failed`, and never tried again. A transition
no handler applies fails the same way. A transition done, failed or cancelled no longer changes.
