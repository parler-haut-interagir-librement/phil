# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The suite `tests/Unit` tests the tokens and their strict reader, the keyed hashes pinned and their ordered keys, the
invitations and their moments, the schedules, the link addresses, `AccessLinks` on a frozen clock across the opening,
the closure and the expiry, the reminders through a Messenger bus, and one contract run against both stores, the DBAL
one on SQLite in memory. In the monorepo, Infection holds the package at 100 % of covered mutants.

The suite `tests/Integration` runs the same contract on a real MySQL 8.4 and PostgreSQL 17, with a reminder claimed and
an answer written from another connection; each server's test reads its URL from `PHIL_TEST_MYSQL_URL` or
`PHIL_TEST_POSTGRESQL_URL` and is skipped without it:

```sh
PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
vendor/bin/phpunit packages/access-links/tests/Integration
```

## Fuzzing

The fuzz target `link-token` feeds the token reader arbitrary text: whatever it accepts must be the text itself and
decode to exactly 32 bytes that encode back to it, and it throws nothing but `InvalidToken`.

```sh
composer fuzz -- link-token --budget=60
```

## Testing code that invites

Build `AccessLinks` on an `InMemoryInvitationStore` and a `MockClock`, keep the token of the issued link, and move the
clock across the closure:

```php
use Symfony\Component\Clock\MockClock;

$clock = new MockClock('2026-11-13 10:00:00');
$links = new AccessLinks(
    new InMemoryInvitationStore(),
    new TokenHasher(str_repeat('k', 32)),
    new LinkFormat(Url::fromString('https://events.example/rsvp')),
    $clock,
    new ReminderSchedule(['P1D']),
);

$issued = $links->invite('poll-1', 'member-1', $clock->now()->modify('+2 days'));
$clock->sleep(86400);
$links->remind($issued->invitation->id, $recordingSender);   // true: no answer yet
$links->respond($issued->token, 'yes');
```
