# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `CalendarSync` | Reads the busy times of a connection into the store; writes and removes events, through the connection's adapter |
| `BusyPeriod` | A time when someone is taken: a start and an end in UTC, tentative or not |
| `BusyPeriods` | Busy periods cut to a window, sorted and joined; at most `MAX` |
| `SyncWindow` | The period read, in UTC, at most `MAX_DAYS` days |
| `CalendarConnection` | A linked calendar: its identifier, its adapter, its calendar and its zone |
| `Credentials` | A bearer token, a user name and a password, or nothing; never serialised |
| `OutboundEvent` | An event the site writes into a calendar |
| `Contract\BusySource` | An adapter that reads busy periods |
| `Contract\CalendarDestination` | An adapter that writes and removes events |
| `Contract\ConnectionRepository` | The application's connections |
| `Contract\CredentialStore` | The application's credentials, for one call |
| `Contract\BusyStore` | Where the application keeps the busy periods read |
| `Contract\OutboundEvents` | The application's events, for the handler of `SyncEvent` |
| `Adapter\IcsFeed` | The adapter `ics`: a published iCalendar feed, read only |
| `Adapter\CalDav` | The adapter `caldav`: a CalDAV collection |
| `Adapter\GoogleCalendar` | The adapter `google`: Google Calendar API v3 |
| `Adapter\MicrosoftGraph` | The adapter `microsoft`: Microsoft Graph v1.0 |
| `ICalendar\ICalendarReader` | The busy periods of an iCalendar text within a window |
| `ICalendar\ICalendarWriter` | An event as the iCalendar object a CalDAV server stores |
| `Message\RefreshBusyTimes` | Asks for the busy times of a connection, by identifier and window |
| `Message\RefreshBusyTimesHandler` | Handles `RefreshBusyTimes` |
| `Message\SyncEvent` | Asks for an event to be written or removed, by identifiers |
| `Message\SyncEventHandler` | Handles `SyncEvent` |
| `Exception\CalendarSyncException` | What the package throws |
| `Exception\InvalidSync` | A value refused |
| `Exception\InvalidCalendarData` | An answer or a calendar that cannot be read, or is too large |
| `Exception\ProviderFailed` | A service that refused, failed or did not answer; its adapter and its status |
| `Exception\UnknownConnection` | A connection, or an adapter, that is not there |

`Adapter\HttpCall`, `Adapter\Json`, `ICalendar\Components`, `ICalendar\EventTimes` and `ICalendar\Periods` are
internal.
