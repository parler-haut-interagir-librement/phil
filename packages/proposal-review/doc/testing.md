# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database server or a network, on the stores in memory and
Symfony's `MockClock`. It tests the value objects and their refusals; the phases at the edges of their windows; each
action in and out of its phase; conflicts by proposal and by author; manual and balanced assignment; the most reviews
and the assigned-only rule; "save and next"; the three visibilities and blindness; standings by mean and median in both
directions; decisions recorded, replaced, applied together and final; the events; the stores; the pair draw, its
balance and its conflicts; and the Bradley–Terry fit — its fixed point, an unbeaten proposal, the order of the log, the
iteration bounds. The DBAL stores run the same contract on SQLite in memory, with their tables, their prefixes, rows
they did not write, and another request that takes a vote's number or an assignment first.

The integration suite, under `tests/Integration`, runs that contract on MySQL and PostgreSQL when
`PHIL_TEST_MYSQL_URL` or `PHIL_TEST_POSTGRESQL_URL` gives a server, and is skipped otherwise. In the monorepo, Infection
holds the package at 100 % of covered mutants.

## The benchmark

`tests/Benchmark/ReviewBench.php` measures the standings of five hundred proposals with three reviews of four criteria
each, the Bradley–Terry fit of two hundred proposals over twenty thousand votes, and the next pair of a voter who
judged five hundred pairs, in that log. In the monorepo, `composer bench` runs it.

## Testing code that uses the engine

Build the board on the stores in memory and a mock clock, and move the clock to the phase the test needs:

```php
use Phil\ProposalReview\Memory\InMemoryCampaignStore;
use Phil\ProposalReview\Memory\InMemoryProposalStore;
use Phil\ProposalReview\Memory\InMemoryReviewStore;
use Phil\ProposalReview\ReviewBoard;
use Symfony\Component\Clock\MockClock;

$clock = new MockClock('2027-01-10 09:00:00 UTC');
$campaigns = new InMemoryCampaignStore();
$campaigns->save($campaign);
$board = new ReviewBoard($campaigns, new InMemoryProposalStore(), new InMemoryReviewStore(), $clock);

$board->submit($campaign->id, 'talk-1', ['u-1']);
$clock->modify('2027-02-05 09:00:00 UTC'); // into the review window
```

To check the events, pass a dispatcher that records what it receives.
