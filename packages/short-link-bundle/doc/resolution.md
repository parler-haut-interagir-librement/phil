# Resolution

`Infrastructure\Http\ShortLinkListener` answers the requests on the short link domains on `kernel.request`, with a
priority of 40, before the routing. A request on any other host goes on untouched, without a query.

## What a request answers

| Request | Answer |
|---|---|
| A method other than `GET` or `HEAD` | `405 Method Not Allowed`, before the link is read |
| The root of a domain | A `302` to `home_url`, or a `404` |
| A path that is no code: a second segment, a character out of the alphabet, more than 64 characters | `404`, without a query |
| A code nobody holds, a link of another locale, a link not yet in force | `404` |
| A link disabled, past its end or out of visits | `410 Gone` |
| A link in force | Its redirect status — `302` by default, or `301`, `307`, `308` — to its target |

A trailing slash is ignored. The `404` and the `410` are thrown as HTTP exceptions, so that the application's error
pages render them. A `GET` and a `HEAD` each count a visit — a person's or a machine's, see below.

The redirect carries `Cache-Control: no-store, private`, so that no browser nor shared cache keeps it and every visit
reaches the count, `X-Robots-Tag: noindex`, and `Referrer-Policy: no-referrer`, so that the target does not learn the
short address that sent the visitor. A permanent status (`301`, `308`) asks browsers to remember the target: a browser
that does goes there without the short domain, and its later visits are not counted.

## The resolver of phil/redirects-bundle

`Application\ShortLinkResolver` turns the link into a redirect rule of phil/redirects-bundle — an exact source on the
domain, the link's target, status and query policy — and the redirects' `LocationBuilder` writes the location. The
query string of the visit is dropped, or appended after the target's own parameters when the link keeps it; the
location is checked once more and sent in its canonical form. A location that cannot be built is not found, and no
visit is counted.

## The domains of the locales

A link without a locale is served on every domain. A link with a locale is served on the domains of that locale and on
the domains without one, and is not found on the domain of another locale. Its address, `ShortLinks::url()`, is written
on the first domain of its locale, or else on the first domain without a locale, or else on the first domain.

## The count of visits

A visit is counted by one `UPDATE` whose conditions are the link's terms at that moment: enabled, from its start,
before its end, under its maximum of people's visits. A person's visit adds to `visits` and sets `last_visit_at`; a
machine's adds to `robot_visits` alone. Two requests racing for the last visit are counted one after the other by the
database: one redirects, the other is gone. The count is synchronous; the recorders are told after the response has
gone, on `kernel.terminate`, and a recorder that fails is logged without failing the others. The listener drops the
visit of a request on `kernel.reset`, so a worker keeps nothing from one request to the next.

## People and machines

Many requests on a short link come from no person: a search engine's crawler, a monitor, a script, a browser fetching
the link ahead of a click that may never come, a messaging service drawing the link's preview the moment it is posted.
Each visit is classified before the link is read, and only the visits of people count:

| Class | Told by | Counted in |
|---|---|---|
| `Human` | Anything below does not match | `visits()`, which the maximum reads, and `lastVisitAt()` |
| `Prefetch` | A purpose header — `Sec-Purpose`, `Purpose`, `X-Purpose`, `X-Moz` — holding the token `prefetch`, `prerender` or `preview` | `robotVisits()` |
| `LinkPreview` | A `User-Agent` naming the preview of a messaging service or a social network: Slack, Discord, Telegram, WhatsApp, Teams, Facebook and iMessage, Mastodon… | `robotVisits()` |
| `Robot` | No `User-Agent`, or one naming a crawler, a script, a monitor or a headless browser: `bot`, `crawl`, `spider`, `curl/`, `python`… | `robotVisits()` |

- **A machine is redirected the same way** as a person, so that a preview shows the target and a prefetch is ready
  when the click comes. It is counted only while the link is in force, by the same statement, and never uses up the
  maximum: a link limited to one visit lets every preview through and stays for the one person who clicks.
- **The order**: the purpose first, then the link previews — their names often hold `bot` —, then the robots.
- **Only the class goes further.** The headers are read and dropped: neither the `User-Agent` nor the address is
  kept, logged or given to the recorders. `ShortLinkVisit::$visitor` carries the class.
- **Bounded**: the first 512 bytes of the `User-Agent` and the first 256 of the purpose headers are read, matched by
  plain search without case, never by a regular expression built from the request.
- **A client that lies is counted as what it claims.** A robot that sends a browser's `User-Agent` counts as a person;
  the classification separates the honest machines, which are most of them, not the hostile ones.
- **The lists grow** with `visitors.link_previews` and `visitors.robots` ([Configuration](configuration.md)); the
  whole classification is replaced through `Application\Contract\VisitorClassifier`
  ([Extension points](extension-points.md)).

## The order of the terms

A link is disabled before anything else, then past its end, then out of visits, then not yet started: a link both out
of visits and not yet started is gone.
