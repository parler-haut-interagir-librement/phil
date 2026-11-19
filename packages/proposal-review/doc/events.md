# Events

When given a PSR-14 event dispatcher, the engine dispatches one event per change, after the store has it. Each event
names what changed by its identifiers only — campaign, proposal, reviewer, vote number —; a listener reads the rest from
the stores. None carries a score, a name or an address, so an event may go to a message queue as it is.

| Event | Dispatched by | When |
|---|---|---|
| `PhaseChanged` | `ReviewBoard::advance()` | The campaign is in another phase than the one last recorded; it carries both phases. |
| `ReviewSubmitted` | `ReviewBoard::review()` | A reviewer scored a proposal, or scored it again. |
| `DecisionRecorded` | `ReviewBoard::decide()` | A pending decision was recorded or replaced. |
| `DecisionsApplied` | `ReviewBoard::applyDecisions()` | Pending decisions were applied; it lists their proposals. Not dispatched when none was pending. |
| `PairwiseVoteCast` | `PairwiseVoting::vote()` | A vote was appended; it carries its number. |

The phases follow the clock whether anyone calls `advance()` or not: `PhaseChanged` is how an application hears about
it — to open the review screens, to remind the reviewers, to close the call. Call `advance()` from a scheduled task
often enough for the precision you need; it does nothing while the phase stays the same.

```php
use Phil\ProposalReview\Event\DecisionsApplied;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class NotifyAuthors
{
    public function __invoke(DecisionsApplied $event): void
    {
        foreach ($event->proposals as $proposal) {
            // read the decision and the authors, and send each author their message
        }
    }
}
```
