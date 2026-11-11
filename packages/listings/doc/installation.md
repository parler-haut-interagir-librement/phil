# Installation

## Requirements

| Package | Version | Why |
|---|---|---|
| PHP | 8.5 or later | Readonly classes, typed constants, `array_all()`, `new` without parentheses. |
| `ext-mbstring` | any | Titles and places are counted in characters, not bytes. |
| `psr/clock` | `^1.0` | The `Responder` reads the instant of a response or a decision on a PSR-20 clock. |

## Installing

```sh
composer require phil/listings
```

## Declaring the kinds

Each kind of listing the application offers has one `KindPolicy`; `KindPolicies` holds them, each kind once.

```php
use Phil\Listings\KindPolicies;
use Phil\Listings\KindPolicy;
use Phil\Listings\ListingKind;
use Phil\Listings\Moderation;

$policies = new KindPolicies([
    KindPolicy::of(ListingKind::of('classified')),
    KindPolicy::of(ListingKind::of('job'), lifetimeDays: 60, moderation: Moderation::BeforePublication, maxOpenResponses: 500),
    KindPolicy::of(ListingKind::of('partner'), minimumAge: 13, reportsToHide: 2),
]);
```

| Rule | Default | Bounds | What it does |
|---|---|---|---|
| `lifetimeDays` | 30 | 1 to 365 | How long a listing stays published, from its publication. |
| `reminderDays` | 3 | 0 to `lifetimeDays` − 1 | How many days before its expiry the author is reminded; 0 for no reminder. |
| `extensionDays` | 30 | 1 to 365 | How many days one extension adds. |
| `maxExtensions` | 3 | 0 to 52 | How many times a listing may be extended. |
| `moderation` | `AfterPublication` | | Moderated before a listing is shown, or after, on reports. |
| `reportsToHide` | 3 | 1 to 100 | How many distinct members' reports hide a published listing. |
| `minimumAge` | none | 0 to 120 | The youngest age that may respond; a listing may ask for an older one. |
| `acceptsResponses` | yes | | Whether listings of the kind take responses at all. |
| `maxOpenResponses` | 100 | 1 to 10 000 | How many pending and accepted responses one listing takes. |

A value out of its bounds throws `InvalidListing`, so that a policy read from configuration fails when the
application boots, not when a member posts.

## Building the responder

```php
use Phil\Listings\Responder;

$responder = new Responder($policies, $blockList, $memberAges, $responseStore, $clock);
```

The block list, the members' ages and the store of responses are the application's
([Extension points](extension-points.md)). The responder keeps no state of its own between calls: it is safe to
share across the requests of a long-running worker.
