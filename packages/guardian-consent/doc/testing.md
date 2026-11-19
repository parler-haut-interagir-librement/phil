# Testing

## The library's tests

The suite `tests/Unit` tests calendar dates across years, leap years and time zones; whole years completed on the day
before, on and after a birthday, and for a birthday on 29 February under both rules; jurisdictions, their bounds and
the brackets they give; identifiers, features, versions and addresses at their bounds; confirmation tokens and their
hashes, with key rotation; the stores in memory; and `GuardianConsent` on a frozen clock — requests, answers, forged,
used and expired tokens, withdrawals, the handover on the day of the age of consent, the sweep and its limits, the
events it dispatches —, and access and erasure.

From the monorepo root:

```sh
vendor/bin/phpunit packages/guardian-consent/tests/Unit
```

The fuzz target `confirmation-token` holds the reader of tokens to its contract:

```sh
vendor/bin/phil-dev fuzz confirmation-token
```

In the monorepo, Infection holds the library at 100 % of covered mutants.

## Testing code that uses the consents

Build `GuardianConsent` on the stores in memory, a notifier that keeps what it is given, and a frozen clock, such as
Symfony's `MockClock`; move the clock to cross a birthday or a delay:

```php
use Symfony\Component\Clock\MockClock;

$clock = new MockClock('2026-11-14 12:00:00', 'America/Toronto');
$consent = new GuardianConsent(
    new InMemoryMinorStore(),
    new InMemoryRequestStore(),
    new InMemoryProofStore(),
    $notifier,                                        // keeps the token it is sent
    new TokenHasher(str_repeat('k', 32)),
    $clock,
);

$consent->register($member, CalendarDate::fromString('2012-11-20'));
$consent->request($member, $guardian, [new Feature('messages')], new NoticeVersion('v1'));
$consent->decide($notifier->token, [new Feature('messages')]);

self::assertTrue($consent->allows($member, new Feature('messages')));

$clock->modify('2026-11-20 00:00:00');               // the 14th birthday, in Quebec

self::assertSame(ConsentStatus::HandoverRequired, $consent->status($member, new Feature('messages')));
```

The stores in memory implement `ResetInterface`: `reset()` empties them between tests that share a container.
