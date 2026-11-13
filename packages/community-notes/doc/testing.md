# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network. It tests the rule — notes
published by two camps and not by one, decided not helpful, too few ratings, no clear majority, each rater in one pair,
the earliest raters first, the bounds —; `CommunityNotes` on the stores in memory and a mock clock — writing, rating
again, refusals, withdrawing, deciding again, the events —; a member's data exported and erased; the qualification
through Symfony Security; and the message to decide again. In the monorepo, Infection holds the package at 100 % of
covered mutants.

`tests/Benchmark/CrossAgreementBench.php` measures a decision among fifty raters over ten thousand ratings, for two
pairs, ten pairs, and no pair found.

## Testing code that uses notes

Build `CommunityNotes` on `InMemoryNoteRepository`, `InMemoryRatingRepository`, Symfony's `MockClock` and a
qualification of the test, with a rule small enough for a few raters:

```php
$notes = new CommunityNotes(
    new InMemoryNoteRepository(),
    new InMemoryRatingRepository(),
    $qualification,
    new MockClock('2026-11-12 10:00:00 UTC'),
    new CrossAgreementRule(minRatings: 2, pairs: 1, minShared: 1),
);
```

Raters who usually disagree are made by rating earlier notes against each other first.
