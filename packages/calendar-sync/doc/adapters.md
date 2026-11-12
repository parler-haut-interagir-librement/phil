# Adapters

Every adapter sends one request at a time, through no proxy — not even one of the environment's, which would resolve
the host past the private network guard —, follows no redirect, bounds the silence of the service to 10 seconds and the
whole request to 30, and reads at most 10 MiB of an answer. A `401` or a `403` is `ProviderFailed::unauthorized()`,
another error status `ProviderFailed::status()`, no answer `ProviderFailed::unreachable()`.

## `ics`: a published feed

`IcsFeed` reads a calendar published as an iCalendar feed: the secret address Google, Microsoft, Apple or Proton give
for a calendar. It only reads.

- **Calendar**: the address of the feed; `webcal://` and `webcals://` are read as `https://`.
- **Credentials**: none. The address is often the feed's only secret: keep it as a credential.
- **Transport**: `phil/safe-http`, which refuses a host on a private network, checks each redirect it follows, and
  stops at 10 MiB; a feed cut there is refused.

## `caldav`: a CalDAV collection

`CalDav` reads and writes a calendar collection of a CalDAV server (RFC 4791): Nextcloud, Fastmail, iCloud, Radicale.

- **Calendar**: the `https` address of the collection, without a query or a fragment.
- **Credentials**: a user name and an application password, or a bearer token.
- **Busy times**: the `free-busy-query` report (§7.10), which answers with times only. A server that answers `400`,
  `404`, `405`, `415`, `422` or `501` to it is asked a `calendar-query` for the times of the events — start, end,
  duration, rule, dates, status, transparency —, expanded by the server; the reader expands them again when the
  server does not.
- **Events**: written with `PUT` at `<collection>/<SHA-256 of the identifier>.ics`, so no identifier can become a
  path, and deleted at that address; `404` and `410` to a removal are not errors.
- **Transport**: Symfony's `NoPrivateNetworkHttpClient` around the client given, so a host that is or resolves to a
  private network is refused. `resolve` pins a host to an address, still checked.
- **Multistatus**: read with `ext-dom`, without network access; a document type is refused.

## `google`: Google Calendar

`GoogleCalendar` reads and writes a Google calendar through the Google Calendar API v3.

- **Calendar**: the calendar identifier, `primary` or an address such as `abc123@group.calendar.google.com`.
- **Credentials**: an OAuth access token, with the scope `calendar.freebusy` to read and `calendar.events` to write.
- **Busy times**: `freeBusy`, which answers with times only. An error of the calendar in the answer is
  `ProviderFailed::refused()` with Google's reason, such as `notFound`.
- **Events**: kept under an identifier derived from the event's, its SHA-256 in hexadecimal, which Google's alphabet
  admits. A write replaces the event, and inserts it when Google answers `404`. A time in a named zone is written in
  it; a time with an offset only, in UTC.

## `microsoft`: Microsoft Graph

`MicrosoftGraph` reads and writes an Outlook calendar of a Microsoft 365 or Outlook.com account, through Microsoft Graph
v1.0.

- **Calendar**: the Graph identifier of a calendar of the signed-in user, or `primary` for their default calendar.
- **Credentials**: an OAuth access token, with `Calendars.ReadBasic` to read and `Calendars.ReadWrite` to write.
- **Busy times**: the calendar view, asking only for each event's start, end, availability and cancellation, in UTC.
  Free and "working elsewhere" events and cancelled ones are left out, tentative ones kept as tentative. At most 20
  pages of 500 events; a next page is followed only on `graph.microsoft.com`.
- **Events**: found again by an extended property that holds the event's identifier, updated when found, created
  otherwise, deleted when found. Times are written in UTC, whole days as midnight to midnight.
