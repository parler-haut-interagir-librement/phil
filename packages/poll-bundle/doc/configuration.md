# Configuration

```yaml
# config/packages/phil_poll.yaml
phil_poll:
    store: doctrine          # or "memory", for tests
    entity_manager: default  # the Doctrine entity manager of the tables
    policy: null             # a service implementing PollPolicy; DefaultPollPolicy when null
    results_cache:
        pool: null           # a cache pool, such as "cache.app"; no count is kept when null
        lifetime: 3600       # the seconds a count is kept at most
```

| Option | Default | What it does |
|---|---|---|
| `store` | `doctrine` | Where polls and ballots are kept: the Doctrine stores, or the in-memory ones, for tests, emptied between two requests. |
| `entity_manager` | `default` | The entity manager of the Doctrine stores. |
| `policy` | `null` | The service that says who may create a poll, vote and manage it. |
| `results_cache.pool` | `null` | The cache pool, a `Symfony\Contracts\Cache\CacheInterface`, where counts are kept until the next ballot; none by default, every count reading every ballot. |
| `results_cache.lifetime` | `3600` | The seconds a count is kept at most, 1 or more. |

## The services

| Service | Public | What it is |
|---|---|---|
| `Phil\PollBundle\Application\Polls` | yes | The entry point of the application. |
| `Phil\PollBundle\Application\PollViews` | yes | The form of a ballot and the results, as a viewer sees them. |
| `Phil\Poll\CountingMethods` | yes | The counting methods: the library's four, the single transferable vote and the application's. |
| `Phil\PollBundle\Domain\Repository\PollRepository` | yes | An alias of the Doctrine or in-memory store of polls. |
| `Phil\PollBundle\Domain\Repository\VoteRepository` | yes | An alias of the Doctrine or in-memory store of ballots and participations. |
| `Phil\PollBundle\Application\Contract\PollPolicy` | no | An alias of the configured policy, or of `DefaultPollPolicy`. |
| `ChoiceCount`, `InstantRunoff`, `Borda`, `Schulze` | no | The library's methods, tagged `phil_poll.counting_method`. |
| `Phil\PollBundle\Domain\Counting\MeekStv` | no | The single transferable vote, tagged `phil_poll.counting_method`. |
| `Phil\PollBundle\Infrastructure\Twig\PollTwig` | no | The functions `phil_poll_ballot()` and `phil_poll_results()`, registered when Twig is installed. |

Every service of the application implementing `Phil\Poll\Contract\CountingMethod` is tagged
`phil_poll.counting_method` by autoconfiguration and added to `CountingMethods`. `Polls` dispatches its events through
`event_dispatcher` when the application has one.

## The store in memory

The stores of `store: memory` are tagged `kernel.reset`: they are emptied between two requests, under PHP-FPM as in a
worker, so that nothing a request writes reaches the next one. A test that reads what an earlier request wrote uses the
Doctrine store on SQLite.

## The cache of the counts

With `results_cache.pool`, `results()`, `resultsFor()`, `election()` and `electionFor()` keep each count in the pool,
under a key that names the poll and the revision of its ballots. Every ballot cast, changed or withdrawn raises the
revision in the store, by one statement, so that the next count reads the ballots again; a count that was running
while a ballot arrived is kept under a revision nobody asks for any more, and expires with its lifetime. Closing a poll
changes nobody's count. The pool holds the counts only: which options won, by how much, never who voted.
