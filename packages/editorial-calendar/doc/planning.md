# Planning

## Planned items

A `PlannedItem` is one publication the calendar plans:

| Property | What it holds |
|---|---|
| `id` | Its identifier: 1 to 128 letters, digits, `.`, `_`, `:` or `-` |
| `content` | The content it publishes, as a `ResourceReference` of `phil/resource` (`article:42`) |
| `title` | Its title, 1 to 500 characters |
| `locale` | Its language, as a language tag (`fr`, `fr-CA`, `zh-Hant-TW`) |
| `author` | Its author, as an `AuthorId` |
| `state` | Where it stands, as an `ItemState` |
| `publishAt` | The moment it is planned for, in UTC, or `null` |
| `section` | The section of the site it belongs to (`news`, `blog`), 1 to 128 letters, digits, `.`, `_`, `:` or `-`, or `null` |

`PlannedItem::plan()` creates an item as an idea; `PlannedItem::restore()` rebuilds one as it was stored, in any state.
Each change gives a new item: `transitionTo()`, `reschedule()`, `edit()` — whose values left `null` are kept —, and
`withoutSection()`.

An `AuthorId` is a stable identifier — a user identifier, a UUID —, never an email address: an address changes and is
personal data. One holding `@` is refused.

## The workflow

| From | To |
|---|---|
| `Idea` | `Drafting`, `Withdrawn` |
| `Drafting` | `Idea`, `Ready`, `Withdrawn` |
| `Ready` | `Drafting`, `Scheduled`, `Published`, `Withdrawn` |
| `Scheduled` | `Ready`, `Published`, `Withdrawn` |
| `Published` | `Withdrawn` |
| `Withdrawn` | `Idea` |

`ItemState::canBecome()` tells whether a step is allowed; `transitionTo()` throws `InvalidTransition` otherwise. A
scheduled item always has a moment: one given to `transitionTo()` replaces the planned one, and an item scheduled
without either is refused. A published item keeps the moment it was published at. `isPending()` is true until an item
is published or withdrawn; a finished item is no longer rescheduled.

## Moments

Every moment is kept in UTC, whatever time zone it was given in: `2026-12-01 09:00 America/Toronto` is stored as
`2026-12-01T14:00:00Z`. Moments are compared by their order, never with `==` or `===`. Views turn them back into days
of a time zone.

## The calendar

`EditorialCalendar::of($items, $batches)` holds the items and the batches by identifier; an identifier is there once,
an item is in one batch at most, and a batch holds only items of the calendar. Each change gives a new calendar:

| Method | Gives |
|---|---|
| `with($item)` | The calendar with the item added, or replacing the one of its identifier |
| `without($id)` | The calendar without the item; its batch loses it, and goes when it held nothing else |
| `withBatch($batch)`, `withoutBatch($id)` | The calendar with a batch added or replaced, or removed |
| `transition($id, $state, $at)` | The calendar with an item in another state |
| `applyBatch($id, $now)` | The calendar with every item of the batch published; see [publication batches](batches.md) |

It answers `items()`, `item($id)`, `batches()`, `batch($id)`, `batchOf($itemId)`, `moment($item)` — the batch's moment
for an item of a batch, else its own —, `dueBatches($now)`, `manualBatches()` and `unscheduled($filter)`: the pending items
with no moment and no batch, the ideas and drafts not yet placed in time, among those a [filter](views.md#filters)
accepts. An unknown identifier throws `UnknownItem`.

The application stores the items and batches as it likes, and rebuilds the calendar with `of()` and `restore()`.
