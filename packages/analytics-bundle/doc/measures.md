# Measures

Beyond page views, a site can count what its visitors do. **Everything here stays in aggregate:** each send advances
counters that stand alone, with no cookie, no visit identifier and no thread from one counter to the next.

State: every type below is implemented and tested. `MeasureCounters` turns one send into counters, for the bundle's
collection endpoint ([collection-and-admission.md](collection-and-admission.md#the-collection-endpoint)).

## The plan of a site

A site declares what it measures in its `MeasurePlan`, the last argument of `CollectionSite::of()`. The `config`
projection builds it from the `goals`, `forms`, `click_names`, `clicks`, `downloads`, `dimensions` and
`server_events` of each site under `phil_analytics.sites`; an application's projection builds it itself:

```php
use Phil\AnalyticsBundle\Domain\Measure\{ClickTracking, ClosedList, DeclaredDimension, Goal, MeasureEvent, MeasurePlan};

$plan = MeasurePlan::of(
    clicks: ClickTracking::Aggregate,              // the default; ClickTracking::Off stops every click measure
    clickNames: ClosedList::of(['register-cta']),
    forms: ClosedList::of(['contact']),
    downloads: ClosedList::downloads(),            // the default list of file extensions
    goals: [
        Goal::onRoute('signup', 'welcome'),
        Goal::onEvent('asked', MeasureEvent::FormSubmit, 'contact'),
    ],
    dimensions: [DeclaredDimension::of('checkout_button', ClosedList::of(['a', 'b']))],
    serverEvents: ClosedList::of(['signup', 'ticket_sold']),
);
```

**A value that is not listed counts nothing.** A forged request creates no counter, and no list can hold a name. The
lists are bounded: 256 values each, 64 goals, 16 dimensions. A server event starts with a letter and holds at most 50
characters.

## What the script sends

The controller of [the collection script](collection-and-admission.md#the-collection-script) takes these values:

| Value | Default | Meaning |
|---|---|---|
| `clicks` | `aggregate` | `off` adds no click listener |
| `downloads` | `[]` | The extensions counted, from `$plan->downloads->toArray()` |
| `dimensions` | `{}` | The value of each declared dimension on this page |
| `status` | `200` | `404` reports a page not found |
| `search-term` | empty | The term of a search results page |
| `search-results` | `-1` | The number of results; negative when unknown |

An event repeats the page's `path`, `referrer`, `verdict` and `dimensions`, with its name in `event`:

| `event` | Fields | When | Counter (metric, dimension, value) |
|---|---|---|---|
| `click` | `name` | An element with `data-phil-analytics-click` is clicked | `clicks`, `name`, the declared name |
| `download` | `extension` | A link to a listed extension is clicked | `events`, `download`, the extension |
| `outbound` | `host`, `link` | A link to another host is clicked | `outbound`, `host`, the host; `outbound_link`, `link`, the reference |
| `form_submit` | `form` | A form with `data-phil-analytics-form` is submitted | `events`, `form`, the declared name |
| `not_found` | none | The page's `status` is 404 | `events`, `not_found`, the class of its source |
| `search` | `term`, `results` | The page has a `search-term` | `search`, `term`, the term; `search`, `outcome`, `found` or `none` |
| `engagement` | `scroll`, `active` | The page is left | `engagement`, `scroll`, a band; `engagement`, `active`, a band |

Every event goes through the same admission as a page view: nothing is sent, or counted, under Do Not Track or Global
Privacy Control. The path of a page not found is not kept: it is an open text, so only the class of the source is.

## Clicks

`ClickTracking::Aggregate` is the default: a counter per declared name, tied to nobody. `ClickTracking::Off` stops
named clicks, downloads and outbound links, in the script and at the server. The bundle has no level per person, as it
keeps no identifier a click could be tied to.

## Goals and conversion

A goal is reached by a declared route or by an event of a declared value; each time adds one to `goals`, `goal`, its
name. A conversion rate divides that counter by the site's visits, both read over the same days:

```php
$reached = $store->counts($site, MeasureNames::GOALS, 'goal', $from, $to)['signup'] ?? 0;
$visits = array_sum($store->counts($site, MeasureNames::VISITS, 'source', $from, $to));

$rate = ConversionRate::of($reached, $visits, $threshold);  // null when either is under the threshold
$rate?->basisPoints;                                        // 1250 for 12.5 %
```

No visit is followed, so one visit that reaches a goal twice counts twice, and a rate can exceed one.

## Outbound links

**Any host is counted**, under `outbound`, `host`; hosts are an open dimension, so read them through
`SmallNumberSuppression` like any other counter. Only the host leaves the page: the path and the query of a link can
hold a token.

**The count of each link** is for the links an application wrote into a page itself. The page gives such a link an
opaque reference, and `OutboundLinkDirectory` says whose it is:

```twig
<a href="{{ link.url }}" data-phil-analytics-link="{{ link.reference }}">{{ link.label }}</a>
```

A reference the directory does not know counts the host only. These counters are exact, so **only the author of the
link and the administrators read them**, through `LinkCountReader`:

```php
$counts = $store->counts($site, MeasureNames::OUTBOUND_LINK, 'link', $from, $to);

LinkCountReader::author($account->reference)->counts($links, $counts);   // the author's links only
LinkCountReader::administrator()->counts($links, $counts);               // every link
```

No shared view holds them ([sharing-reports-alerts.md](sharing-reports-alerts.md)).

## Pairs of routes

A page view from another page of the same site adds one to `transitions`, `route_pair`, `home>event`. Both sides must
be declared routes. A counter per pair, and no thread between pairs: no individual path can be put back together.

## Reading depth and active time

When a page is left, or Turbo replaces it, the script sends one more time, once, how far the page was read
(`0`, `25`, `50`, `75`, `100`) and for how many seconds it was visible. The server keeps bands only (`ScrollDepth`,
`ActiveTime`). This send happens on `pagehide`, and needs no cookie or storage: the measure lives in the controller,
for as long as the page. The bands are counted for the site as a whole, as crossing them with routes would shrink
each counter towards the size where it designates a person.

## Search terms

`SearchTerm::valueOf()` lower-cases a term and collapses its spaces. A term of 1 character or more than 64, or that
holds an `@`, a `://` or a run of six digits, is not counted: it may name someone. Terms are an open dimension: read
them through `SmallNumberSuppression`, which shows the frequent ones only. The monthly rollup forgets the rare ones
([retention.md](retention.md)), and shared views never show them.

## Variants

A variant of an experiment, or any other label, is a **declared dimension**: the bundle does not read the feature
flags. The page hands the value it already computed to the script:

```twig
<body data-controller="phil--analytics-bundle--collect"
      data-phil--analytics-bundle--collect-dimensions-value="{{ {checkout_button: variant}|json_encode|e('html_attr') }}">
```

A page view adds to `pageviews` and, for a visit, `visits`, under the dimension. A goal reached adds to `goals.<name>`
under the dimension, so that variants convert side by side:

```php
$conversions = $store->counts($site, 'goals.signup', 'checkout_button', $from, $to);
$visits = $store->counts($site, MeasureNames::VISITS, 'checkout_button', $from, $to);
```

A dimension cannot be named `route`, `device`, `country` or `source`.

## Events the server counts

Some events never reach a page: an account created, a ticket sold, a notification delivered. `ServerEventCounter`
counts them from the application's own code, for any site the projection holds, under the same rules as the
collection endpoint:

```php
use Phil\AnalyticsBundle\Application\Measure\ServerEventCounter;

$events->count(SiteId::fromString('spring-con'), 'signup', ['plan' => 'pro']);   // true: counted
$events->count(SiteId::fromString('spring-con'), 'login');                       // false: not declared
```

| Counter (metric, dimension, value) | Advanced by |
|---|---|
| `server_events`, `event`, the event | Every counted event |
| `server_events.<event>`, the dimension, the value | Each declared dimension sent with one of its declared values |

The event must be in the site's `server_events`; a dimension that is not declared, or a value outside its list, is
dropped, and a site that is suspended or retired counts nothing. **Nothing that names a person becomes a counter**:
pass the plan of an account, never its reference. `count()` returns whether the event was counted, so that a typo
in an event name shows in the application's tests. The day is the UTC day of the clock, as for a page view.

One `ServerEventCounter` serves every site: an application that runs several sites on one platform counts each site's
events against that site's own declaration.
