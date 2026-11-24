# Configuration

The bundle's configuration lives under `phil_scheduling_poll`. Only the links are required:

```yaml
# config/packages/phil_scheduling_poll.yaml
phil_scheduling_poll:
    store: doctrine
    entity_manager: default
    connection: default
    links:
        keys: ['%env(SCHEDULING_POLL_LINK_KEY)%']
        page: 'https://example.org/scheduling-poll/answer'
        parameter: token
    link_lifetime: P30D
    max_respondents: 500
    closing:
        frequency: '5 minutes'
        batch: 100
    erasure:
        frequency: '1 hour'
    schedule:
        cache: cache.app
        lock_factory: lock.factory
    mailer:
        from: 'Polls <polls@example.org>'
    respondent_addresses: ~
    privacy:
        account_kind: user
    kit_subdirectory: ~
```

| Option | Default | What it does |
|---|---|---|
| `store` | `doctrine` | Where polls and respondents are kept: `doctrine`, or `memory` for tests, emptied between two requests. |
| `entity_manager` | `default` | The Doctrine entity manager of the tables of the polls. |
| `connection` | `default` | The Doctrine DBAL connection of the tables of the links. |
| `links.keys` | — | The keys of the links' keyed hash, 32 bytes or more each, up to eight: the first hashes, every key finds. |
| `links.page` | — | The absolute address of the answer page, without a fragment; a query is kept. |
| `links.parameter` | `token` | The query parameter that carries the token. |
| `link_lifetime` | `P30D` | An ISO 8601 duration: how long a link still opens after the deadline, and how long after the closing the names and addresses are erased. |
| `max_respondents` | `500` | The most respondents a poll takes, from 1 to 10 000. |
| `closing.frequency` | `5 minutes` | How often the schedule closes the polls past their deadline. |
| `closing.batch` | `100` | The polls closed at most each time, from 1 to 1000. |
| `erasure.frequency` | `1 hour` | How often the schedule erases the names and addresses past their delay. |
| `schedule.cache` | `cache.app` | The cache pool where the schedule keeps its state. |
| `schedule.lock_factory` | `lock.factory` | The lock factory of the schedule's lock; without one, the schedule holds none. |
| `mailer.from` | `null` | The sender of the mail, `Name <address>`; the Mailer's own headers when `null`. |
| `respondent_addresses` | `null` | The service, a `RespondentAddresses`, that finds the address of a respondent who joined with an account. |
| `privacy.account_kind` | `user` | The kind of the references of the accounts the data subjects of `phil/privacy-bundle` are. |
| `kit_subdirectory` | `null` | Where the recipes of the kit are, under the components directory; the Toolkit's subdirectory when `null`. |

A page with a fragment, a page on `localhost`, a lifetime that is not an ISO 8601 duration in its designator form
(`P30D`, `P1W`, `PT12H`), or no key, stops the build.
