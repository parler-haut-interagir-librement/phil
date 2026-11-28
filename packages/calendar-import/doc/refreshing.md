# Refreshing a source

`CalendarImporter` reads a source again and says what changed since its last read. It keeps nothing itself: the state
of each source and the editors' overrides are behind two ports, which the application implements.

```php
use Phil\CalendarImport\CalendarImporter;
use Phil\CalendarImport\ChangeKind;
use Phil\CalendarImport\Source\IcsUrlSource;

$importer = new CalendarImporter(IcsUrlSource::create(), $stateStore, $overrideStore, $clock);
$outcome = $importer->refresh($source);

foreach ($outcome->changesOf(ChangeKind::Added) as $change) {
    // $change->draft: an ImportedDraft to create in the application's calendar
}
```

## Snapshots

Each successful read gives a `Snapshot`: when it was taken, and the `EventVersion` of each event, by its key. A
version holds the event's `SEQUENCE`, its `LAST-MODIFIED`, the SHA-256 of its content — every field the library gives,
`DTSTAMP`, `SEQUENCE` and `LAST-MODIFIED` left out — and the SHA-256 of each field an editor may replace. A snapshot
holds versions, never events: it is small, and holds no text of the feed.

`Snapshot::toArray()` and `Snapshot::fromArray()` write and read it back as plain arrays, for a JSON column or a cache;
`fromArray()` throws `InvalidSource` on data that does not hold together.

## Differences

`$snapshot->compare($previous)` gives a `SnapshotDiff`:

- **added** — keys absent from the previous snapshot; from no snapshot, every event is added;
- **changed** — keys whose content differs, each with its `ChangeReason` and the `Field`s it changes;
- **removed** — keys absent from this snapshot.

The reason of a change is, in this order: a new `SEQUENCE`, else a new `LAST-MODIFIED`, else the content alone. A
`SEQUENCE` or a `LAST-MODIFIED` that moves while the content stays the same is no change: feeds that stamp every event
at every export do not flood the application with changes.

An occurrence changed by a `RECURRENCE-ID` has its own key, so it is added, changed and removed on its own.

## What a refresh does

1. It loads the state of the source (`SourceState`), or starts one.
2. It fetches the source with the validators of its last answer.
3. **When the source fails** (`SourceUnavailable`), the state keeps its last snapshot, records the failure — its
   `FailureKind`, its message, its time, its status — and counts the failures in a row. The outcome reports no change:
   the last good state stays until a read replaces it.
4. **When nothing changed** (`304 Not Modified`), the state keeps its snapshot, takes the new validators and the time
   of the check, and clears the failure.
5. **When a calendar is read**, a new snapshot is taken and compared with the last one. An event refused this time that
   was read before keeps its last version, so that a broken line in a feed is not read as a removal.
6. The overrides of the source are applied to the drafts, and a field an editor replaced that the source changed too
   is reported as a conflict of its change.
7. The state is saved, and an `ImportOutcome` returned.

| `ImportOutcome` | Holds |
|---|---|
| `read` | whether a calendar was read this time |
| `state` | the state saved |
| `changes` | the `Change`s: kind (`Added`, `Changed`, `Removed`), key, draft (not for a removal), reason, conflicts |
| `drafts` | the draft of every event read, by key |
| `rejected` | the events refused, each with its `Rejection` |
| `isFailure()` | whether the source failed this time |

## Refreshing on a schedule

`refreshDue($sources)` reads again the sources that are due by the `RefreshPolicy`, and leaves the others. A bundle or
an application calls it from a scheduled task; the library itself schedules nothing.

```php
use Phil\CalendarImport\State\RefreshPolicy;

new RefreshPolicy(interval: 3_600, retry: 300, backoff: 86_400);
```

- A source never checked is due.
- A source that succeeded is due `interval` seconds after its last check (an hour by default).
- A source that fails is due `retry` seconds after its last check, doubled for each failure in a row past the first,
  `backoff` seconds at most (5 minutes, then 10, 20… up to a day by default): a source that is down is asked less and
  less often, and never less than once per `backoff`.

Each delay is 60 seconds to 7 days, and `backoff` is never shorter than `retry`; otherwise `InvalidLimits` is thrown.

## Editor overrides

An editor may change an imported event without losing the change at the next read. An `EditorOverride` names the key
of the event, and replaces any of its title, location, description and tags, or hides it:

```php
use Phil\CalendarImport\Override\EditorOverride;

new EditorOverride('board-games-night@calendar.example.org', title: 'Board games night', tags: ['games']);
new EditorOverride('closed-day@calendar.example.org', hidden: true);
```

A null field is the source's. The override is kept apart from the events read, behind the `OverrideStore` port, and
applied again at each refresh: it survives every read, until the editor takes it away.

When the source changes a field an override replaces, the change of that event lists the field in its `conflicts`:
the application may tell the editor that the source has a new title under theirs. The draft still carries the
editor's value.

## Drafts

An `ImportedDraft` is an imported event ready to become an event of the application's calendar. It is neutral: it
belongs to no calendar library, and an adapter of the application turns it into its own events (see
[Extension points](extension-points.md)).

| Field | Holds |
|---|---|
| `sourceId`, `key`, `uid` | where the event comes from: the source, its key there, its UID there |
| `recurrenceId` | the occurrence it replaces, for a changed occurrence |
| `start`, `end`, `allDay`, `floating`, `timezone` | its times |
| `title`, `description`, `location`, `tags` | its texts, the editor's in place of those replaced |
| `url`, `status`, `rule`, `additions`, `exclusions` | its address, status and recurrence |
| `hidden` | whether an editor hid it |
| `overridden` | the `Field`s an editor replaced |

`isCancelled()` tells a cancelled event or occurrence.

## Stores

| Port | Keeps | Implemented by |
|---|---|---|
| `State\SourceStateStore` | the `SourceState` of each source: snapshot, validators, times, failure | the application or a bundle (a table, a cache) |
| `Override\OverrideStore` | the `EditorOverride`s of each source | the application or a bundle |

The tests use in-memory stores, under `tests/Double`; they are not part of the library, which keeps nothing from one
request to the next.
