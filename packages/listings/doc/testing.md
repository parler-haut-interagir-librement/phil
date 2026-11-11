# Testing

## The library's tests

The suite `tests/Unit` tests the identifiers and keys and their bounds; the folding and bounds of titles and places;
each type of criterion, its bounds and the meeting of choices, ranges and flags, and of whole sets of criteria; the
policies, their defaults and every bound; every transition of a listing, to the second — submission under both
moderations, approval and rejection, reports that hide a listing and its restoration, edits, expiry, reminders,
extensions from the expiry or from now, closing and removal — and every transition its status refuses; responses and
their transitions; and the responder's refusals in their order, over in-memory ports and a frozen clock.

From the monorepo root:

```sh
vendor/bin/phpunit packages/listings/tests
```

The library is held to 100 % covered mutation score by Infection.

## The benchmark

`tests/Benchmark/ListingsBench.php` measures what a search costs per listing it reads — the meeting of six criteria
with four wanted ones, with and without the visibility check — and what a submitted form costs: a title and a set of
criteria. `composer bench`, from the monorepo root, runs it with the other benchmarks.

## Testing code built on the library

Listings and responses need no clock: give each transition the instant of the test. The `Responder` takes a PSR-20
clock, such as Symfony's `MockClock`, and three ports that are a few lines each in memory:

```php
use Phil\Listings\KindPolicies;
use Phil\Listings\KindPolicy;
use Phil\Listings\ListingKind;
use Phil\Listings\Responder;
use Symfony\Component\Clock\MockClock;

$clock = new MockClock('2026-03-14 10:30:00');
$partners = KindPolicy::of(ListingKind::of('partner'), minimumAge: 18);
$responder = new Responder(new KindPolicies([$partners]), $noBlocks, $agesByMember, $memoryStore, $clock);

$clock->sleep(30 * 86400);
self::assertSame(Refusal::NotOpen, $responder->refusal($listing, $member));
```
