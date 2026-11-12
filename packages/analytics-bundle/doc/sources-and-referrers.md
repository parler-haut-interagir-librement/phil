# Sources and referrers

**The source of a page view is classified by the bundle's own code** into one of six classes, over host lists
the application supplies. Only the class is counted, never the referrer. No referrer-parsing library is
installed, and only the referrer's origin is ever read: its path and query are not.

State: `HostList`, `ReferrerClassifier` and `ReferralKind` are implemented and tested. The bundle wires
`ReferrerClassifier` from `phil_analytics.hosts`, with short default lists of search engines and social networks that
the application replaces ([configuration.md](configuration.md#domain-settings)).

## `ReferralKind`

| Case | Value | Meaning | `isVisit()` |
|---|---|---|---|
| `Direct` | `direct` | No referrer, or one with no web origin (`android-app://...`) | yes |
| `Search` | `search` | A search engine | yes |
| `Social` | `social` | A social network | yes |
| `Website` | `website` | Any other site | yes |
| `Internal` | `internal` | The site itself, or another origin the application declares its own | no |
| `Spam` | `spam` | On the spam list: discarded before anything is counted | no |

**A visit is a page view whose source is not the site itself** (`isVisit()`). Counting visits this way needs no
visitor identifier ([counters.md](counters.md#no-unique-visitors-visits-instead)).

## `ReferrerClassifier`

```php
$classifier = new ReferrerClassifier(
    HostList::of(['google.{}', 'duckduckgo.com']),   // search
    HostList::of(['facebook.com']),                  // social
    HostList::of(['free-traffic.test']),             // spam
);

$own = [
    Origin::fromString('https://example.com'),
    Origin::fromString('https://shop.example.com'),
    Origin::fromString('https://login.example.net'), // e.g. the return from an identity provider
];

$classifier->classify(null, $own);                                   // Direct
$classifier->classify('android-app://com.example.mail', $own);       // Direct
$classifier->classify('https://example.com/events?x=1', $own);       // Internal
$classifier->classify('https://login.example.net/cb?code=abc', $own);// Internal
$classifier->classify('https://www.google.co.uk/search?q=x', $own);  // Search
$classifier->classify('https://m.facebook.com/', $own);              // Social
$classifier->classify('https://free-traffic.test/', $own);           // Spam
$classifier->classify('https://blog.example.com/', $own);            // Website: an undeclared subdomain is another site
```

`classify(?string $referer, iterable<Origin> $ownOrigins): ReferralKind` applies this order:

1. No referrer, an empty one, or one without an http(s) origin: `Direct`.
2. An origin equal to one of `$ownOrigins` (exact scheme, host and port): `Internal`.
3. Host on the spam list: `Spam`.
4. Host on the search list: `Search`.
5. Host on the social list: `Social`.
6. Anything else: `Website`.

Pass as `$ownOrigins` the site's declared origins (`CollectionSite::$origins`) **plus any origin the application
treats as the site itself**, such as the return from its identity provider; otherwise a sign-in round trip would
count as a new visit.

The referrer to classify is the referrer *of the page view* — what the visitor's browser had as the page's
referrer — not the `Referer` of the collection request, which is the measured page itself.

## `HostList`

`Phil\AnalyticsBundle\Domain\ValueObject\HostList` is a list of host names a referrer host is matched against.

```php
$list = HostList::of(['example.com', 'google.{}']);
$list->matches('www.example.com');   // true: an entry matches itself and every subdomain
$list->matches('badexample.com');    // false: matching is on label boundaries
$list->matches('WWW.Example.com.');  // true: case and a trailing dot are ignored
$list->matches('google.co.uk');      // true
$list->matches('google.attacker.example'); // false
$list->entries;                      // ['example.com', 'google.{}'], normalized and deduplicated
HostList::empty()->matches('example.com'); // false
```

Entries are trimmed and lower-cased; duplicates are dropped. An entry that is not a host name (empty, a URL, a
path, an empty label, a space) throws `InvalidValue`.

Matching costs the labels of the host, not the length of the list: the literal entries are kept in a hash set and
the host is looked up once per suffix (`www.example.com`, `example.com`, `com`). The entries with a `{}` label are
kept in a tree of labels read from the right, where `{}` is one more branch, and the host walks down that tree one
label at a time. A list of thousands of referrer-spam hosts, literal or templated, checked on every page view, stays
a few lookups, and no list is too long to match: no pattern is built from the entries.

### The `{}` label

**A label written `{}` stands for a public suffix, never for a registrable domain**: one top-level label (`com`,
`fr`), or one of the generic second-level labels `ac`, `co`, `com`, `edu`, `go`, `gob`, `gov`, `ne`, `net`, `or`,
`org` followed by a two-letter country code (`co.uk`, `com.au`). So `google.{}` matches `google.com`,
`www.google.co.uk` and `google.fr`, but not `google.attacker.example` nor `www.google.evil.co.uk`.

Why: anyone can register `attacker.example`. If `{}` matched any domain, a forged referrer would be classified as a
search engine, and a spam entry would discard legitimate sites under the same prefix.

This is a fixed approximation, not the Public Suffix List: a public suffix outside that pattern (a three-label
suffix, a country-specific second level not in the list) is not matched by `{}`; list such hosts explicitly.

## Where the lists come from

Public-domain lists are the intended source: for example the CC0 search-engine and social-network list and the
CC0 referrer-spam list published by the Matomo project, read and converted to `HostList` entries by the
application and given under `phil_analytics.hosts`. The bundle's defaults are ten search engines and twelve social
networks, and no spam host; it installs no referrer-parsing library — in particular none whose data file is under a
copyleft licence.
