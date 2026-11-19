# Installation

```sh
composer require phil/proposal-review
```

The package needs PHP 8.5 or later, `psr/clock` and `psr/event-dispatcher`; `symfony/polyfill-php86` gives
`\SortDirection` before PHP 8.6. It needs a clock — `symfony/clock` in a Symfony application — and takes an event
dispatcher when one is given.

| Package | Constraint | For |
|---|---|---|
| `doctrine/dbal` | `^4.5`, suggested | The stores under `Dbal\`, in a database. |

The services keep no state between calls. In a Symfony application, declare the stores — the ones under `Dbal\` on the
application's DBAL connection, or the application's own (see [Storage](storage.md)) — and the two services:

```yaml
# config/services.yaml
services:
    Phil\ProposalReview\Dbal\DbalCampaignStore: ~
    Phil\ProposalReview\Dbal\DbalProposalStore: ~
    Phil\ProposalReview\Dbal\DbalReviewStore: ~
    Phil\ProposalReview\Dbal\DbalVoteLog: ~
    Phil\ProposalReview\Contract\CampaignStore: '@Phil\ProposalReview\Dbal\DbalCampaignStore'
    Phil\ProposalReview\Contract\ProposalStore: '@Phil\ProposalReview\Dbal\DbalProposalStore'
    Phil\ProposalReview\Contract\ReviewStore: '@Phil\ProposalReview\Dbal\DbalReviewStore'
    Phil\ProposalReview\Contract\VoteLog: '@Phil\ProposalReview\Dbal\DbalVoteLog'

    Phil\ProposalReview\ReviewBoard:
        arguments:
            $events: '@event_dispatcher'

    Phil\ProposalReview\Pairwise\PairwiseVoting:
        arguments:
            $events: '@event_dispatcher'
```

The clock is autowired from `Psr\Clock\ClockInterface`, which Symfony aliases to its own clock, and the DBAL stores
from `Doctrine\DBAL\Connection`. Each DBAL store takes a table prefix as its second argument, `phil_review_` by default.
Their tables come from their own schema, as [Storage](storage.md#the-tables) shows. The in-memory stores under
`Memory\` serve tests and demonstrations.
