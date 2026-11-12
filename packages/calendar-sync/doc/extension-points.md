# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

## Ports the application implements

| Port | What it gives |
|---|---|
| `Contract\ConnectionRepository` | The connections, by identifier |
| `Contract\CredentialStore` | The credentials of a connection, refreshed, for one call |
| `Contract\BusyStore` | Where the busy periods read are kept, replaced window by window |
| `Contract\OutboundEvents` | The events the handler of `SyncEvent` writes, as they are when it runs |

## Adapters of its own

An application reads another service by implementing `Contract\BusySource`, and writes to one by implementing
`Contract\CalendarDestination`, under a name of its own — a lowercase word of 32 characters at most —, then passing
the adapter to `CalendarSync` among its sources or its destinations. A connection names the adapter it uses.

An adapter reads busy periods only, gives them in any order, and throws `ProviderFailed` or `InvalidCalendarData`:
`CalendarSync` cuts, sorts, joins and bounds them.

## Transports

| To | Do |
|---|---|
| Read through a scoped client, or test doubles | Pass a client to the adapter; `CalDav` still wraps it in `NoPrivateNetworkHttpClient`, and no request goes through a proxy |
| Pin a CalDAV host to an address | Pass `resolve` to `CalDav`: the address is still checked |
| Read feeds with other limits | Pass a `SafeHttpClient` with other `Limits` to `IcsFeed` |
| Date the events CalDAV stores | Pass a clock to `CalDav` |

## What stays closed

The value objects, the adapters and `CalendarSync` are final. The iCalendar reader reads times only: an application
that needs the titles of another calendar reads it with its own code, not this one.
