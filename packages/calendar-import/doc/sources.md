# Sources

A source is where events come from. The library reads it through a port, `EventSource`, and ships one adapter,
`IcsUrlSource`, for an iCalendar feed read by its address.

## A calendar to import

`CalendarSource::of($id, $address, $zone)` names a calendar the application imports:

- `$id` — its identifier in the application, 1 to 255 bytes on one line; states, overrides and drafts are kept under it;
- `$address` — its address. `webcal://` and `webcals://` are read as `https://`. The address is then read by
  `phil/url`: `http` or `https`, a public host name, no user name nor password. Anything else throws `InvalidSource`;
- `$zone` — the zone of the times the feed writes without one, when it names none itself (`X-WR-TIMEZONE`); UTC by
  default. A zone PHP does not know throws `InvalidSource`.

```php
use Phil\CalendarImport\Source\CalendarSource;

$source = CalendarSource::of('library-events', 'webcal://calendar.example.org/public.ics', 'America/Toronto');
```

## The port

```php
interface EventSource
{
    public function fetch(CalendarSource $source, ?FetchValidators $validators = null): Fetched;
}
```

A source answers with a `Fetched`: either a calendar read (`$fetched->result`, a `ReadResult`) with the validators of
the answer, or word that nothing changed since the validators sent (`isNotModified()`). A source that gives no
calendar this time throws `SourceUnavailable`, whose `kind` says why:

| `FailureKind` | When |
|---|---|
| `Unreachable` | no answer in time, a broken connection, a name that does not resolve |
| `Status` | an error status; `$exception->status` holds it |
| `Refused` | the address, or a redirect, leads to a private network; a redirect not allowed; too many redirects |
| `TooLarge` | more bytes than allowed |
| `Invalid` | an answer the reader refuses whole |

The message of a `SourceUnavailable` from `IcsUrlSource`, which the state keeps as the failure's message, names the
feed by its address without its query and fragment — `"https://calendar.example/feed.ics?…" answered with the status
404.` —, since a private feed often keeps its secret there; the addresses in a transport error are shown the same way.

## The iCalendar feed

`IcsUrlSource` reads a feed through `phil/safe-http`.

```php
use Phil\CalendarImport\Source\IcsUrlSource;

$feeds = IcsUrlSource::create();                       // its own client
$feeds = IcsUrlSource::create($httpClient, $limits, redirects: 2);
$feeds = new IcsUrlSource($safeHttpClient, $reader);   // a client and a reader set up by the application
```

`create()` builds a `SafeHttpClient` with 10 seconds to connect and wait, 30 seconds for the whole read, as many bytes
as the reader's `ReaderLimits` allow, and no redirect by default: a feed that moved fails as `Refused`, so that the
application sees it and updates its address, rather than following it forever.

Each read:

1. sends `GET` with `Accept: text/calendar, text/plain;q=0.5, */*;q=0.1`, and, when validators are given,
   `If-None-Match` and `If-Modified-Since`;
2. on `304 Not Modified`, gives `Fetched::notModified()` with the validators of the answer, or those sent when the
   answer holds none: nothing changes;
3. on another status outside 2xx, fails as `Status`;
4. on a body cut at the byte limit, fails as `TooLarge`;
5. reads the body with `ICalendarReader`, in the zone of the source; a text refused whole fails as `Invalid`, or as
   `TooLarge` when the bound it passed is the bytes.

### Conditional reads

`FetchValidators` holds the `ETag` and the `Last-Modified` of the last answer. A value that is empty, longer than 1,024
bytes or holds a control character is not kept, and so never sent back. The importer keeps the validators in the
state of each source, and sends them at the next read.

## Security

- **No private network.** `phil/safe-http` resolves each host and refuses an address that is, or resolves to, a
  private, loopback, link-local or reserved network — at the first request and at each redirect. A feed address typed
  by an editor can never make the server read its own network.
- **No surprise redirect.** None is followed by default; when some are allowed, each is checked like the first address.
- **Bounded time and size.** The read stops at its deadline and at its byte limit; the reader then bounds the work done
  on what was read (see [Reading iCalendar](reading.md#bounds)).
- **No credentials in addresses.** `phil/url` refuses an address with a user name or a password.
- **Text only.** Every text of a feed comes out as plain text, to be escaped where it is shown.
- **Private events stay out.** An event marked `CLASS:PRIVATE` or `CLASS:CONFIDENTIAL` is refused.
