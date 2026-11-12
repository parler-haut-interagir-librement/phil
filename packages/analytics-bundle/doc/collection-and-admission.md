# Collection and admission

How a page view reaches the counters, or does not: the script that sends it, the endpoint that receives it, and every
decision in between.

| Piece | State |
|---|---|
| `Origin`, `CollectionSite`, `SiteStatus` | Implemented and tested |
| `AdmissionRule`, `Admission` | Implemented and tested, over the `Verdict` of `phil/bot-detection-bundle` |
| `MeasureCounters` | Implemented and tested: the counters a send advances ([measures.md](measures.md)) |
| `CollectionProjection`, `CollectionRateLimiter`, `CrawlerDetector`, `CountryResolver`, `CounterStore`, `OutboundLinkDirectory` | Contracts, each with a default adapter the application can replace ([configuration.md](configuration.md#the-adapters)) |
| `Collector`, `CollectionRequest`, `CollectionOutcome` | Implemented and tested: the order of collection, from a request to the counters |
| The collection endpoint, `CollectController` and the route `phil_analytics_collect` | Implemented and tested |
| The collection script (one call per page, no cookie, no local storage) | A Stimulus controller, tested with Node |

## The collection script

With StimulusBundle, the controller is found from the bundle's `assets/package.json`; it goes on the page's body:

```twig
<body data-controller="phil--analytics-bundle--collect"
      data-phil--analytics-bundle--collect-endpoint-value="{{ url('phil_analytics_collect') }}">
```

Each time it connects — the page loads, or Turbo renders the next one —, it sends one page view:

| Field | Value |
|---|---|
| `path` | `location.pathname`: the query is left out, it may carry a token |
| `referrer` | the referrer's origin only, without path, query or fragment: `document.referrer`'s for the first page of a document, the previous page's for a page Turbo renders afterwards; `null` when there is none, or it is not `http(s)` |
| `verdict` | the `verdict` value, else the verdict `phil/bot-detection-bundle`'s controller left on the root element (`data-phil-bot-verdict`), else `unknown` |
| `from` | the path of the previous page of the same site, when there is one, for the pair of routes |
| `dimensions` | the `dimensions` value the page gives, when it gives one |

The page then reports its events, and when it is left, how far it was read: see [measures.md](measures.md).

**The referrer is an origin, never a path.** The server reads only the referrer's origin
([sources-and-referrers.md](sources-and-referrers.md)), and a path can name a person — a profile, a private
document, a search —, so the script does not send it.

**A Turbo navigation is not a new visit.** Turbo Drive renders the next page in the same document, and
`document.referrer` keeps the referrer of the first page; sent again, a visitor arriving from a search engine and
browsing five pages would count six visits from search. The controller keeps the address of the page it last saw,
in memory, for the life of the document, and sends its origin — the site's own, classified internal — as the
referrer of the next page.

It sends nothing when the browser sends Do Not Track (`navigator.doNotTrack === '1'`) or Global Privacy Control
(`navigator.globalPrivacyControl === true`), as the endpoint would count nothing anyway. It sends by a `fetch` kept
alive (`keepalive: true`), which survives the page being left, **without credentials** (`credentials: 'omit'`), so no
cookie of the collection origin goes with a page view; as `text/plain`, so that a cross-origin endpoint needs no
preflight. Only a browser without `fetch` falls back to `navigator.sendBeacon()`, which always sends the collection
origin's cookies: keep the collection origin free of cookies. It sets no cookie and writes nothing to local or
session storage: there is nothing to consent to.

## The order of collection

The order is fixed, and **each step only ever narrows**: nothing later can turn a refusal into a write. A body longer
than collection reads is refused before the first step.

1. **Resolve the origin** the request claims (`Origin::ofRequest()`). No origin: refuse without a write.
2. **Find the site that declares it** (`CollectionProjection::siteDeclaring()`). No site: refuse without a
   write.
3. **Check the site's status** (`SiteStatus::collects()`). A suspended or retired site is refused **before the
   rate limiter is consumed**, so it spends no budget and leaves no digest in the limiter's state.
4. **Consume the rate limiter** for this site and this keyed client digest
   ([rate-limiting.md](rate-limiting.md)). A client address that is not an address is refused before; over budget, or
   limiter unavailable: drop the page view (fail closed).
5. **Decide the admission** (`AdmissionRule::decide()`), which checks the status again first.
6. **Write** what the admission allows: the audience counters, or the robot-verdict counter, or nothing.

## Which site a request belongs to: `Origin`

`Phil\AnalyticsBundle\Domain\ValueObject\Origin` is a web origin `scheme://host[:port]`, normalized: lower-case
scheme and host, default port dropped. Only `http` and `https` are origins. The value is read as a browser reads it, by
PHP's WHATWG URL parser on which `phil/url` rests: an international host in its ASCII form, as browsers send it in
`Origin`, so that `https://bücher.example` declared matches the header `https://xn--bcher-kva.example`; an IPv6 address
in brackets; a backslash in a `Referer` taken for the slash that ends the host. `localhost` and IP addresses are
origins too: the declared origins decide which ones collect.

```php
Origin::fromString('HTTPS://Example.COM:443')->toString();      // 'https://example.com'
Origin::ofUrl('https://example.com/a?code=secret#state');       // Origin https://example.com (path, query, fragment dropped)
Origin::ofUrl('android-app://com.example');                     // null
Origin::ofRequest('https://a.example', 'https://b.example/p');  // https://a.example — the Origin header wins
Origin::ofRequest('null', 'https://b.example/p');               // https://b.example — opaque Origin: falls back to Referer
Origin::ofRequest(null, 'https://b.example/p');                 // https://b.example
Origin::ofRequest('https://a.example/path', 'https://b.example/p'); // null — a malformed Origin is not rescued by the Referer
Origin::ofRequest(null, null);                                  // null — refuse
Origin::ofUrl('https://example.com@evil.example/');             // null — user information, never taken for the host
Origin::ofUrl('https://evil.example\\@example.com/');            // Origin https://evil.example, as a browser reads it
```

| Method | Behaviour |
|---|---|
| `fromString(string)` | Strict form (declared by the application, or an `Origin` header): no path, not even `/`, no query, fragment or credentials, even empty. Throws `InvalidValue` otherwise |
| `ofUrl(string): ?Origin` | The origin of a full URL such as a `Referer`; `null` when there is no http(s) origin, or the URL carries credentials or whitespace/control characters |
| `ofRequest(?string $originHeader, ?string $referer): ?Origin` | The `Origin` header if present, non-empty and not `null`, parsed strictly; otherwise the origin of the `Referer`; otherwise `null` |
| `equals(Origin)`, `toString()`, `__toString()` | Comparison on scheme, host and port; `scheme://host[:port]` |
| Properties | `scheme`, `host`, `port` (`null` for the default port) |

A forged request that imitates a declared origin remains possible: the origin check is not authentication. The
rate limiter, crawler detection, the robot verdict and the spam list bound it.

## What collection knows about a site: `CollectionSite`

`Phil\AnalyticsBundle\Domain\ValueObject\CollectionSite` is the shape of one entry of the collection
projection: a site, its declared origins, its declared routes, its status, and nothing else — no personal
information.

```php
$site = CollectionSite::of(
    SiteId::fromString('spring-con'),
    [Origin::fromString('https://example.com'), Origin::fromString('https://www.example.com')],
    DeclaredRoutes::of([DeclaredRoute::of('home', '/')]),
    SiteStatus::Active,
);
$site->declares(Origin::fromString('https://WWW.example.com')); // true
$site->declares(Origin::fromString('http://example.com'));      // false: another scheme is another origin
$site->site; $site->origins; $site->routes; $site->status;
```

`of()` refuses a site with no origin (no request could belong to it) and an origin declared twice
(`https://example.com` and `https://example.com:443` are the same origin).

**Collection reads this projection, never the application that maintains it.** The application writes the
projection when a site's declaration changes and when a site is suspended, reinstated or retired; the
collection endpoint only reads it, ideally with a database role that can read nothing else
([extension-points.md](extension-points.md#collectionprojection)).

## Whether a site collects: `SiteStatus`

| Case | `collects()` | Meaning |
|---|---|---|
| `SiteStatus::Active` | `true` | Collected |
| `SiteStatus::Suspended` | `false` | Refused until reinstated |
| `SiteStatus::Retired` | `false` | Refused for good; the application deletes its counters (`CounterStore::deleteSite()`) |

## What a page view does: `AdmissionRule` and `Admission`

```php
$admission = new AdmissionRule()->decide(
    $site->status,                                  // SiteStatus
    PrivacySignals::fromHeaders($dnt, $secGpc),     // privacy-signals.md
    $crawlers->isDeclaredCrawler($userAgent),       // bool, from the CrawlerDetector port
    $classifier->classify($referrer, $site->origins), // ReferralKind, sources-and-referrers.md
    Verdict::fromSubmitted($submittedVerdict),      // from phil/bot-detection-bundle
);
```

`decide()` returns the first matching case, in this order:

| Order | Condition | `Admission` | `isRefusal()` | `advancesAudience()` | `advancesRobotVerdictCounter()` |
|---|---|---|---|---|---|
| 1 | The site does not collect | `SiteNotCollecting` | yes | no | no |
| 2 | `DNT: 1` or `Sec-GPC: 1` | `OptedOut` | no | no | no |
| 3 | The user agent declares itself a crawler | `DeclaredCrawler` | no | no | no |
| 4 | The referrer is on the spam list | `ReferrerSpam` | no | no | no |
| 5 | The verdict is `Robot` | `RobotVerdictOnly` | no | no | yes |
| 6 | The verdict is `Unknown` | `NoVerdict` | no | no | no |
| 7 | Otherwise (verdict `Human`) | `CountAudience` | no | yes | no |

`Admission` is a string-backed enum (`site_not_collecting`, `opted_out`, `declared_crawler`, `referrer_spam`,
`robot_verdict_only`, `no_verdict`, `count_audience`). `isRefusal()` distinguishes a request refused outright
from one accepted and not counted.

Design decisions:

- **A privacy signal counts nothing, anywhere**, not even in the robot-verdict counter: it comes before the
  verdict.
- **A robot verdict advances only an aggregate robot-verdict counter, never the audience.** `Collector` keeps it per
  site and UTC day in the counter store, under the metric `robot_verdicts` (`MeasureNames::ROBOT_VERDICTS`).
- **A missing or unrecognized verdict is not a robot.** The verdict is read strictly by
  `Verdict::fromSubmitted()` (`' HUMAN '` is `Unknown`). An unknown verdict counts nothing at all: a detector
  that fails in the browser must not turn every person into a robot, which would also hide the silence of the
  detector behind a wave of robots. A silent detector shows as a robot-verdict counter at zero.
- **The status is checked again first**, although step 3 of collection already checked it, so that no later
  change in the endpoint can turn a refusal into a write.

## The collection endpoint

`Infrastructure\Http\CollectController`, route `phil_analytics_collect`, receives one page view or event per `POST`
and hands it to `Application\Collection\Collector`, which follows the order above. Import the route under the prefix of
your choice ([configuration.md](configuration.md#the-collection-endpoint)):

```yaml
# config/routes/phil_analytics.yaml
phil_analytics:
    resource: '@PhilAnalyticsBundle/config/routes.php'
    prefix: /analytics
```

```twig
<body data-controller="phil--analytics-bundle--collect"
      data-phil--analytics-bundle--collect-endpoint-value="{{ url('phil_analytics_collect') }}">
```

The route only answers `POST`, is stateless (no session is started), and answers without a body, with
`Cache-Control: no-store`. What became of the request is a `CollectionOutcome`:

| Outcome | Status | When |
|---|---|---|
| `Counted` | 204 | Admitted: the audience counters, or the robot-verdict counter, advanced |
| `NotCounted` | 204 | Accepted and counted nowhere: a privacy signal, a declared crawler, referrer spam, no verdict |
| `Refused` | 403 | No site declares the origin, the site does not collect, or the client address is not an address |
| `OverBudget` | 429 | Over the site's budget for this client, or the limiter could not answer |
| `TooLarge` | 413 | A body over `Collector::MAX_BODY_BYTES` (4096 bytes) |

A `NotCounted` request answers like a counted one, so that a script cannot tell whether a visitor was counted.

**The body comes from the client and can hold anything.** A body announced longer than 4096 bytes is refused before it
is read, and one sent without a length is read up to one byte past the limit, never further. It is decoded to a depth of
three (an object of fields, one of which, `dimensions`, is an object of strings), and each field is checked for its type
before use: junk counts nothing instead of raising an error.

What an admitted request writes:

| Request | Counters, for the site and the UTC day |
|---|---|
| A page view | `pageviews` by `route`, `device` and `country`; `visits` by `source` when the referrer starts a visit; then what `MeasureCounters::ofPageView()` adds — the pair of routes, the declared dimensions, the goals the route reaches ([measures.md](measures.md)) |
| An event (a body with an `event` field) | What `MeasureCounters::ofEvent()` returns, and no page view |
| A robot verdict | `robot_verdicts` by `verdict`, value `robot`, and nothing in the audience |

`Collector` can also be called directly, with a `CollectionRequest` holding the `Origin`, `Referer`, `DNT` and `Sec-GPC`
headers, the client address, the user agent and the body: for an endpoint of the application's own, behind another
framework or a queue.

## What the process serving collection must also guarantee

These are operational rules the bundle cannot enforce, and that its design assumes:

- The client address never reaches a log: no access log on the collection origin, no address in application logs
  or error trackers.
- The collection process holds the salt store's credentials and its own alone.
- Its database role can increment counters and read the collection projection, and nothing else.
- The collection script is served from the application's own origin, with no cookie and no local storage, and
  only sends what the tables of the script's page views and events list: never the query or the fragment of a page.
