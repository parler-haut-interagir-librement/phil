# Extension points

The library is in its `0.x` releases: its ports, value objects and exceptions may change in a minor release, said in
the changelog. They are meant to be stable from `1.0`.

## Ports

| Port | Implemented by | Does |
|---|---|---|
| `Source\EventSource` | `IcsUrlSource`, or another protocol | gives the events of a calendar, or says nothing changed |
| `State\SourceStateStore` | the application or a bundle | keeps the state of each source between two reads |
| `Override\OverrideStore` | the application or a bundle | gives the editors' overrides of a source |

A source of another protocol — a calendar API, a federated feed — implements `EventSource`: it gives a `ReadResult` of
`SourceEvent`s, each with a UID, and throws `SourceUnavailable` when it gives nothing. The snapshots, differences,
overrides and drafts then work alike, whatever the protocol.

A store keeps the `Snapshot` as `toArray()` writes it — plain arrays, small, without the texts of the feed — beside
the validators, the times and the failure of the source. It keeps no state in memory between two requests.

## Writing to a calendar

The library gives `ImportedDraft`s and depends on no calendar library. An adapter of the application, or of a bundle
built on this library, turns the changes of an `ImportOutcome` into its own calendar's events:

| Change | The adapter |
|---|---|
| `Added` | creates an event from the draft, marked with `sourceId` and `key`, so that it can be found again |
| `Changed` | finds the event by `sourceId` and `key`, and applies the draft; `conflicts` tells which editor fields the source changed under them |
| `Removed` | finds the event by `sourceId` and `key`, and removes or unpublishes it |

A draft that is `hidden` stays out of the published calendar; one that `isCancelled()` is shown cancelled, or removed,
as the calendar does with its own events. A draft keeps its zone, whether it is a whole day or floating, and its
`RecurrenceRule`, additions and exclusions from `phil/recurrence`, so that the adapter needs no other reading of the
feed.

A bundle on this library would add:

- a scheduled task that calls `CalendarImporter::refreshDue()` for the sources the application lists;
- Doctrine stores for the states and the overrides;
- the adapter above, from `ImportedDraft` to the draft type of its calendar, which writes each change through the
  calendar's own service — schedule, apply, remove.

## Limits and policy

`ReaderLimits`, given to `ICalendarReader` and to `IcsUrlSource::create()`, set the bounds of a feed; `RefreshPolicy`,
given to `CalendarImporter`, sets when a source is read again. A `SafeHttpClient` set up by the application — its own
timeouts, a proxy, a user agent — is given to the `IcsUrlSource` constructor.
