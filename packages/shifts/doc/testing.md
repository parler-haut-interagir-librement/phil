# Testing

## The package's tests

The tests are PHPUnit tests in `tests/Unit`, run from the root of the monorepo with `composer test`, or on their own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

They run without a kernel, a database or a network. `tests/Fixtures/Shifts` builds the shifts of a Saturday of a
convention — an info desk, a cloakroom — and its instants. In the monorepo, Infection holds the package at 100 % of
covered mutants.

## Testing an application's rosters

A roster is tested with instants of the test's choosing; the library never reads the system's clock:

```php
use Phil\Shifts\Roster\Roster;
use Phil\Shifts\Exception\ShiftRefused;

$roster = Roster::open($shift)->signUp('ada', [], new DateTimeImmutable('2026-11-20T10:00:00Z'));

try {
    $roster->signUp('ada', [], new DateTimeImmutable('2026-11-20T10:01:00Z'));
} catch (ShiftRefused $refused) {
    self::assertSame(ShiftRefused::ALREADY_ON_ROSTER, $refused->reason);
}
```

## The fuzz target

`tests/Fuzz/shift-plan.php` reads documents nobody wrote with `ShiftPlan::fromJson()`, checks that a plan read back
writes the same, then reads a roster of each shift from the places of the same text: each step returns or throws a
`ShiftsException`. Its seeds are in `tests/Fuzz/corpus/shift-plan`.

```sh
composer fuzz -- shift-plan
```

## The benchmark

`tests/Benchmark/RosterBench.php` measures a roster at its bounds — 1,000 places, a reserve of 100 and 1,000
interested —: a sign-up into the reserve, a withdrawal that promotes, an allocation of 500 places, reading the roster
back from its storage, and the minutes held over 200 rosters.

```sh
composer bench
```
