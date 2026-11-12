# Devices and countries

Two dimensions derived from the request at ingestion, each kept to a closed list, and each derived from
something that is then forgotten.

| Piece | State |
|---|---|
| `DeviceClass` | Implemented and tested |
| `CountryCode`, `GeoDatabaseFreshness` | Implemented and tested |
| `CountryResolver` (the lookup) | Port; the default `UnknownCountryResolver` counts every country unknown |

## Devices: `DeviceClass`

**The device is kept in three classes, and nothing finer**: desktop, tablet, mobile. A finer class (a model, a
browser version) adds nothing an aggregate needs and much of what a fingerprint is made of. The class comes from
a small rule over the `User-Agent`, not from a device detection library.

```php
DeviceClass::fromUserAgent($request->headers->get('User-Agent', ''))->value; // 'desktop' | 'tablet' | 'mobile'
```

The rule, case-insensitive:

1. **Tablet** if the user agent contains `iPad`, `Tablet` (but not `Tablet PC`), `PlayBook`, `Kindle` or `Silk/`;
   or contains `Android` without `Mobile`.
2. **Mobile** if it contains `Mobi`, `iPhone`, `iPod`, `Android`, `Windows Phone`, `BlackBerry`, `BB10` or
   `Opera Mini`.
3. **Desktop** otherwise, including an empty user agent.

Tablet comes first because tablet user agents also carry mobile tokens. Known consequences:

- An iPad that presents itself as a Mac is counted as a desktop: the user agent is all the rule reads.
- A Windows desktop whose user agent carries `Tablet PC` (pen-input support) is a desktop; a Windows Phone
  user agent carrying `Tablet PC` is mobile.

## Countries: `CountryCode`

**The country is derived from the client address in memory, then the address is forgotten.** The country is the
only thing about the address a counter ever holds.

```php
$country = CountryCode::fromString('ca');
$country->code;        // 'CA'
$country->isKnown();   // true
$country->toString();  // 'CA'

$unknown = CountryCode::unknown();
$unknown->code;        // null
$unknown->toString();  // '(unknown)' — CounterKey::UNKNOWN
$country->equals($unknown); // false
```

`fromString()` accepts exactly two ASCII letters (an ISO 3166-1 alpha-2 code) and upper-cases them; anything else,
including a trailing newline, throws `InvalidValue`. The bundle does not check that the code is assigned.

The country is unknown when the lookup cannot answer, or when its database is too old to be used.

### The lookup: `CountryResolver`

```php
interface CountryResolver
{
    public function countryOf(#[SensitiveParameter] string $ip): CountryCode;
}
```

The adapter neither logs nor stores the address, and answers `CountryCode::unknown()` when it cannot tell —
including when its database is past the age its licence allows. The bundle's own, `UnknownCountryResolver`, answers
unknown for every address: an application with a geolocation database names its adapter under
`collection.country_resolver`. See [extension-points.md](extension-points.md#countryresolver) for an adapter over a
local GeoLite2-format database.

## Database freshness: `GeoDatabaseFreshness`

Some geolocation database licences bound how long a build may be used. Past that bound, **the country is
counted unknown rather than looked up in a database the licence no longer covers**: a stale database fails to
"unknown", never to a guess.

```php
$freshness = GeoDatabaseFreshness::maxAgeDays(30);
$freshness->maxAgeDays;                       // 30
$freshness->isUsable($builtAt, $now);         // bool
```

| Case | `isUsable()` |
|---|---|
| `$now` within `$builtAt + maxAgeDays` days (inclusive) | `true` |
| `$now` one second past that | `false` |
| `$builtAt` later than `$now` (a clock or a file that cannot be trusted) | `false` |

`maxAgeDays()` refuses less than one day. The age is measured from the build date the adapter passes; what bound
the licence actually sets (for instance a number of days after the *next* release rather than after the build)
is for the application to translate into `maxAgeDays` and `$builtAt`.
