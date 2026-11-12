# Counters

**A counter is the only thing the bundle's design stores.** A counter is a number keyed on a site, a UTC day, a
metric and one value of one declared dimension. No account identifier, IP address, cookie, device identifier,
instant finer than the day, user agent or referrer URL is ever part of a key or stored next to it.

State: `SiteId`, `UtcDay` and `CounterKey` are implemented and tested; the storage is the `CounterStore` port, whose
default adapter keeps the counters in a Doctrine connection ([configuration.md](configuration.md#the-counters)).

## The site: `SiteId`

`Phil\AnalyticsBundle\Domain\ValueObject\SiteId` is the reference of a measured site, the unit every counter
belongs to. It is **opaque**: the application chooses its form (a slug, a UUID).

```php
$site = SiteId::fromString('spring-con');
$site->value;          // 'spring-con'
(string) $site;        // 'spring-con'
$site->equals(SiteId::fromString('spring-con')); // true; comparison is case-sensitive
```

`fromString()` refuses only what cannot be a stable key: an empty string, surrounding whitespace, a control
character, or more than `SiteId::MAX_LENGTH` (255) bytes. A site is never a person: nothing in this reference
may identify a visitor.

## The day: `UtcDay`

`Phil\AnalyticsBundle\Domain\ValueObject\UtcDay` is **always a UTC day**, fixed once at ingestion from the
instant the page view arrived, and the same for every site.

```php
UtcDay::of(new DateTimeImmutable('2026-12-31 23:30', new DateTimeZone('America/Toronto')))->value; // '2027-01-01'
$day = UtcDay::fromString('2028-02-29');  // read back a stored day, strict YYYY-MM-DD
$day->start();                            // DateTimeImmutable 2028-02-29T00:00:00+00:00
$day->next();                             // UtcDay '2028-03-01'
$day->isBefore(UtcDay::fromString('2028-03-01')); // true
$day->equals($other); $day->toString(); (string) $day;
```

Why: a counter holds a number, not the instants that produced it, so a day boundary moved after the fact (another
time zone) cannot be honoured. An aggregate is therefore never recomputed in another time zone, and a UTC day
never has 23 or 25 hours. `fromString()` refuses anything that is not a real calendar day in `YYYY-MM-DD` form
(`2027-02-29`, `2026-13-01`, a time part, a trailing newline).

## The key: `CounterKey`

`Phil\AnalyticsBundle\Domain\ValueObject\CounterKey` says what a counter counts.

```php
$key = CounterKey::of(
    SiteId::fromString('spring-con'),
    UtcDay::of($now),
    'pageviews',   // metric
    'route',       // dimension
    'event',       // one value of that dimension
);
$key->site; $key->day; $key->metric; $key->dimension; $key->value;
$key->equals($other); // all five parts equal
```

Validation:

| Part | Rule |
|---|---|
| `metric`, `dimension` | `CounterKey::NAME_PATTERN`: 1 to 64 characters among `a-z`, `0-9`, `_`, `.`, `-`, starting with a letter |
| `value` | Not empty, at most `CounterKey::MAX_VALUE_LENGTH` (128) bytes, no control character (`\x00`–`\x1F`, `\x7F`) |

Two reserved values are valid values of any dimension:

| Constant | Value | Meaning |
|---|---|---|
| `CounterKey::OTHER` | `(other)` | Everything not shown on its own: at ingestion, a path that matches no declared route ([declared-routes.md](declared-routes.md)); on read, the group of counters under the small-number threshold ([small-number-suppression.md](small-number-suppression.md)) |
| `CounterKey::UNKNOWN` | `(unknown)` | A dimension a page view carries no value for, such as the country of an address the lookup cannot place ([devices-and-countries.md](devices-and-countries.md)) |

### The invariant the type cannot enforce

`CounterKey` checks the *shape* of a key; **it cannot tell an identifier from a dimension value**. A dimension
whose values are open (a user name, an order number, a full URL) becomes an identifier as soon as one value
matches one person. **Keeping each dimension to a closed list of values is the application's job.** The bundle
helps where it can: routes are normalized to declared names, sources to six classes, devices to three, countries
to ISO codes — each a closed list.

### One page view, several counters

A key holds one value of **one** dimension, so a page view counted on several dimensions increments one counter
per dimension. Cross-dimension counters (route × country) are deliberately not part of the key: each extra
dimension multiplies the number of counters and shrinks each one towards the size where it designates a person.

```php
foreach (['route' => $route, 'device' => $device->value, 'country' => $country->toString()] as $dimension => $value) {
    $store->increment(CounterKey::of($site, $day, 'pageviews', $dimension, $value));
}
```

Metric and dimension names are the application's choice; `MeasureNames` holds those the bundle's collector writes.

## No unique visitors; visits instead

**The bundle counts no unique visitor**, because counting one needs an identifier, even hashed and salted.
**A visit is a page view whose source is not the site itself** (`ReferralKind::isVisit()`,
[sources-and-referrers.md](sources-and-referrers.md)). It is less exact than unique visitors, and it is the
only measure that keeps to aggregates:

```php
if ($referral->isVisit()) {
    $store->increment(CounterKey::of($site, $day, 'visits', 'source', $referral->value));
}
```

## Storing counters: the `CounterStore` port

`Phil\AnalyticsBundle\Application\Contract\CounterStore` has a default adapter, `DbalCounterStore`, and the application
can name its own:

| Method | Contract |
|---|---|
| `increment(CounterKey $key): void` | One atomic upsert (`INSERT ... ON CONFLICT DO UPDATE SET n = n + 1`, or the equivalent), so concurrent page views never lose a count and a store that only increments needs no other privilege |
| `counts(SiteId $site, string $metric, string $dimension, UtcDay $from, UtcDay $to): array<string, int>` | The stored counters of one metric and one dimension over an **inclusive** range of days, summed per dimension value. Unsuppressed: the caller applies the small-number rule on read |
| `deleteSite(SiteId $site): void` | Deletes every counter of a site, when the site is retired |

See [extension-points.md](extension-points.md#counterstore) for another adapter.
