# Testing

## The package's tests

The tests are PHPUnit tests in `tests/Unit`, run from the root of the monorepo with `composer test`, or on their own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

They run without a kernel, a database or a network. The ports are implemented in memory under `tests/Support`:

- `MemoryAudience` and `MemoryExclusions` answer from the members they are given, sorted as the port requires;
- `MemoryFacts` implements every fact port and remembers the questions asked;
- `MemoryMembershipStore` keeps the members of each segment and the writes made;
- `RecordingDispatcher` and `FixedClock` stand for the PSR dispatcher and clock.

`RecalculationTest` runs a recalculation where members stay, enter, lose the tier, unsubscribe and leave the audience.
Port implementations that break their contract are tested too. In the monorepo, Infection holds the package at 100 %
of covered mutants.

## The benchmark

`tests/Benchmark/SegmentsBench` freezes a segment of two conditions over an audience of 5 000 members, read by pages of
500, with exclusions:

```sh
composer bench -- packages/audience-segments/tests/Benchmark
```

## Testing an application's port

A port is tested through a segment, so that the library checks what it returns:

```php
use Phil\AudienceSegments\AudienceConditions;
use Phil\AudienceSegments\SegmentJson;
use Phil\AudienceSegments\Segments;
use Symfony\Component\Clock\MockClock;

$json = new SegmentJson(AudienceConditions::types(tags: new DbalPeopleTags($connection)));
$segment = $json->read('press', '{"version":1,"name":"Press","condition":{"type":"person.tagged","operands":{"tags":["press"]}}}');
$segments = new Segments(new NewsletterSubscribers($connection), $exclusions, new MockClock());

self::assertSame(['person:7', 'person:9'], $segments->freeze($segment)->members);
```
