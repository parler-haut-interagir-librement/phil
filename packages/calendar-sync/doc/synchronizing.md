# Synchronizing

## Connections

A `CalendarConnection` is a calendar of someone's, linked to the site:

| Field | What it holds |
|---|---|
| `id` | The identifier the application gives it, 1 to 128 bytes |
| `provider` | The name of the adapter: `ics`, `caldav`, `google`, `microsoft`, or one of the application's |
| `calendar` | The calendar in the adapter's terms: the address of a feed or of a collection, a calendar identifier |
| `timezone` | The zone of the times a feed writes without one, and of its whole days; UTC by default |

The application keeps its connections and gives them through `Contract\ConnectionRepository`. A connection holds no
credential.

## Credentials

`Contract\CredentialStore` gives the `Credentials` of a connection at each call: `Credentials::bearer()` for an OAuth
access token, `Credentials::basic()` for a user name and an application password, `Credentials::none()` for a feed.

The application obtains the tokens, keeps them encrypted, and refreshes them before giving them. The package never
keeps them: `Credentials` cannot be serialised, and a dump of it shows its kind only. When a service refuses them,
`ProviderFailed::isUnauthorized()` is true, so the application can refresh the token, or ask the member to link the
calendar again.

## Busy times

`CalendarSync::refreshBusy()` reads the busy periods of a connection within a `SyncWindow` — at most 400 days —, cuts
them to the window, sorts them and joins those that overlap or touch, then gives them to `Contract\BusyStore`, which
replaces what the connection had in that window. A joined period is tentative only when all it joins are.

When the service fails, or its answer cannot be read, the exception is thrown before the store is called: the periods
read last stay as they were. A calendar that gives more than 10,000 periods in a window is refused rather than half
read, as a calendar half read would leave times free that are not.

A `BusyPeriod` holds a start, an end, in UTC, and whether it is tentative. Nothing else is read from the calendar, so
nothing else can be stored.

## Events written

`CalendarSync::publish()` writes an `OutboundEvent` into a connection; writing the same identifier again replaces it.
`CalendarSync::withdraw()` removes it; an event that is not there is not an error.

An `OutboundEvent` holds:

| Field | What it holds |
|---|---|
| `uid` | Its identifier, stable across writes: 1 to 255 visible ASCII characters |
| `title` | 1 to 255 characters |
| `start`, `end` | In the event's zone, the end excluded; midnight to midnight for whole days |
| `allDay` | Whole days |
| `description`, `location`, `url` | Optional: 8,000 characters, 255 characters, an address of `phil/url` |
| `sequence` | Its revision, 0 or more |

What the event shows is the application's choice: a site that only blocks the time writes a title such as "Busy" and
nothing else.

## Messages

Two messages let a worker do the synchronizing, each carrying identifiers only:

| Message | Carries | Its handler |
|---|---|---|
| `RefreshBusyTimes` | The connection, the start of the window in UTC, its days | Reads the busy times of the window |
| `SyncEvent` | The connection, the event's identifier | Asks `Contract\OutboundEvents` for the event as it is now, writes it, or removes it when there is none |

`RefreshBusyTimes::of($connectionId, $from, $days)` writes the start as `DateTimeInterface::ATOM`. Because the handler of
`SyncEvent` reads the event when it runs, a message sent before a change writes the change, and one sent for an event
deleted since removes it.

## Failures

| Exception | When |
|---|---|
| `UnknownConnection` | The connection is unknown, or no adapter of its name is registered for what was asked |
| `ProviderFailed` | The service refused the credentials, answered an error, refused the request, or did not answer |
| `InvalidCalendarData` | Its answer is not what it should be, or is larger than allowed |
| `InvalidSync` | A value is refused: a window, a connection, an event, credentials, a calendar address |

All implement `Exception\CalendarSyncException`. Their messages name the adapter and the status, never an address, a
token or a body — the address of a feed can be its secret —, and a transport error is not chained, as its message can
hold the address.
