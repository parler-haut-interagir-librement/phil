# The feed

`config/routes.php` declares two routes, `GET`: the calendar's feed, served by `CalendarFeedController`, and a member's
own, served by `PersonalFeedController`.

| Route | Path | What it answers |
|---|---|---|
| `phil_calendar_ics` | `/calendar.ics` | The events as an iCalendar file to subscribe to |
| `phil_calendar_personal_ics` | `/personal/{token}.ics` | A member's own calendar, at the address of their secret |

## The calendar's feed

It takes `host=kind:reference` and `tag=word` to keep the events of one host or one tag.
 The events with an occurrence from a month ago through a year ahead, written by `ICalendar` as RFC 5545 says: a
`VEVENT` each, its rule as `RRULE`, its dates added as `RDATE` and its exceptions as `EXDATE`; times with their `TZID`,
in UTC, or as dates for whole days; a rule's end of the start's kind, as RFC 5545 §3.3.10 asks — the end day of a timed
event is written as the last moment of that day in its time zone, in UTC, `UNTIL=20270101T045959Z`, and the end time of
a whole-day event as its day; texts escaped, lines folded at 75 octets between characters; `STATUS:CANCELLED` for a
cancelled event.
The configured `name` is the calendar's `X-WR-CALNAME`. `ICalendarResponse` serves it as `text/calendar`.

Only the events are gathered: each is read as far as its first occurrence of the period, never through all of them.
The answer is `Cache-Control: public, no-cache` with an `ETag` made of the events it holds and their versions, and of
the name. A calendar or a shared cache that sends it back in `If-None-Match` is answered `304 Not Modified`, without
the file being written; the tag changes when an event is changed, or enters or leaves the year.

`Calendar::events($from, $through, $hostKind, $hostReference, $tag)` gives those events for an application's own
feed, and `ICalendar::of($events, $stamp, $product, $name)` writes the same file for an application's own selection.

Both feeds read their events within the request's [budget](events.md#the-budget-of-a-request): past it, the file holds
the events read so far.

## A member's feed

`/personal/{token}.ics` answers the member whose secret `token` is — 64 hexadecimal digits, issued by `PersonalFeeds`
([Members](members.md#the-feed-of-a-member)) — with their calendar from a month ago through a year ahead: what they
attend, host and follow, less what they hide. `ICalendar::ofOccurrences($occurrences, $stamp, $product, $name)` writes
each occurrence as a `VEVENT` of its own times, without its event's rule, its `UID` the occurrence's —
`Occurrence::id()`, the event's identifier and the start in UTC —, its `URL` the page of that date when it has one: one
date of a weekly series stands alone in the member's calendar.

A token that does not have that form matches no route; one malformed, unknown or revoked is answered
`404 Not Found` with an empty body, as any address that does not exist. Every answer is
`Cache-Control: private, no-cache`, `X-Robots-Tag: noindex, nofollow` and `Referrer-Policy: no-referrer`: no shared
cache keeps it, no robot indexes it, and no page it links to learns its address. It carries an `ETag` of its
occurrences and their events' versions, and of the name; a client that sends it back is answered `304 Not Modified`.

The calendar on a page is the `PhilCalendar:Calendar` component ([views.md](views.md)); there is no JSON feed.
