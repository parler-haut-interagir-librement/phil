# phil/listings

**The core of listings: a classified ad, a job offer, a search for partners — written, moderated, answered and
expired by the same rules.**

phil/listings is the part every kind of listing shares. A listing has a title, typed criteria a search can meet, an
area shown to everyone with an exact place shown only to the people its author accepts, and a minimum age. It is
published at once or after a moderator's approval, hidden by enough reports, reminded before it expires, extended a
bounded number of times and closed by its author. Members respond to it; a response carries no message, and only a
response the author accepts lets the application put the two of them in touch, with the consent of each.

It is for any PHP application where members post offers or requests to one another: small classified ads between
members, the job offers of a community, people looking for partners for an activity, a marketplace between members
and sellers. Each of those is a kind of listing with its own `KindPolicy` — lifetime, reminder, extensions,
moderation, reports, minimum age, open responses — and its own fields and screens, built on this core.

It is a plain PHP library, written from scratch, whose requirements are `ext-mbstring` and `psr/clock`. Listings and
responses are immutable: each transition takes the policy of the listing's kind and an instant, and returns a new
value, so the whole life of a listing is tested to the second and stored as plain values. What the library cannot
know is a port: who blocked whom (`BlockList`), how old a member is (`MemberAges`) and where the responses live
(`ResponseStore`). Every value is bounded — titles, labels, criteria, choices, days, reports, responses — so that
nothing a member types or a configuration says can make a listing unbounded.

## Status

| Part | State |
|---|---|
| Identifiers, kinds, moderation reasons, titles, places | Implemented and tested |
| Criteria — choices, integer ranges, flags — and their meeting with a search | Implemented and tested |
| Kind policies, bounded, and their registry | Implemented and tested |
| The life of a listing: draft, submission, moderation before or after publication, reports, edits, expiry, reminders, extensions, closing, removal | Implemented and tested |
| Responses — pending, accepted, declined, withdrawn — and the `Responder` rules: visibility, own listing, blocks, minimum age, one open response per member, open responses per listing | Implemented and tested |
| Persistence, forms, screens, notifications, conversations, payment | Not in this library: they belong to the application or to a bundle built on it |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, declaring the kinds. |
| [Listings](doc/listings.md) | Titles, places, the minimum age, the statuses and every transition. |
| [Criteria](doc/criteria.md) | Choices, ranges and flags, and how a search meets a listing. |
| [Moderation and expiry](doc/moderation-and-expiry.md) | Moderation before or after publication, reports, expiry, reminders and extensions. |
| [Responses](doc/responses.md) | Responding, accepting, declining, withdrawing, and the rules of the `Responder`. |
| [Extension points](doc/extension-points.md) | The block list, the members' ages and the store of responses. |
| [Exceptions](doc/exceptions.md) | `ListingsException` and its exceptions. |
| [Testing](doc/testing.md) | The library's tests, mutation bar and benchmark, and how to test code built on it. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/listings
```

```php
use Phil\Listings\Criteria;
use Phil\Listings\Criterion;
use Phil\Listings\KindPolicies;
use Phil\Listings\KindPolicy;
use Phil\Listings\Listing;
use Phil\Listings\ListingId;
use Phil\Listings\ListingKind;
use Phil\Listings\Location;
use Phil\Listings\MemberId;
use Phil\Listings\Responder;
use Phil\Listings\ResponseId;
use Phil\Listings\Title;

$partners = KindPolicy::of(ListingKind::of('partner'), lifetimeDays: 30, minimumAge: 13);
$policies = new KindPolicies([$partners]);

$listing = Listing::draft(
    ListingId::of('01JC4Z9X8Y7W6V5T4S3R2Q1P0N'),
    $partners,
    MemberId::of('member-42'),
    Title::of('Two players wanted for a weekly campaign'),
    Criteria::of([Criterion::choice('day', ['sat']), Criterion::range('players', 2, 2)]),
    Location::area('Plateau', '4200 Saint-Laurent'),
    18,
    $clock->now(),
)->submit($partners, $clock->now());   // published at once: the kind is moderated after publication

$responder = new Responder($policies, $blockList, $memberAges, $responseStore, $clock);
$response = $responder->respond($listing, ResponseId::of('01JC5A…'), MemberId::of('member-7'));

$accepted = $responder->accept($listing, $response);   // both consent: the application may open a conversation
```

`$blockList`, `$memberAges` and `$responseStore` are the application's: see [Extension points](doc/extension-points.md).

## Requirements

PHP 8.5 or later, `ext-mbstring` and `psr/clock`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
