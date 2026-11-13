# Events

`Polls` dispatches an event after each action, through the PSR-14 dispatcher of the application — `event_dispatcher`
in Symfony — when there is one. Each event carries the identifier of the poll, and nothing else: not the voter, not
the choices.

| Event | Dispatched when |
|---|---|
| `PollCreated` | A poll was created. |
| `VoteCast` | A ballot was cast, anonymous or not. |
| `VoteChanged` | A ballot was changed, by its voter or with its receipt. |
| `VoteWithdrawn` | A ballot was withdrawn. |
| `PollClosed` | A manager closed the poll. A poll that reaches its closing raises no event: nothing runs at that instant. |
| `PollDeleted` | A poll was deleted with its ballots. |

All are in `Phil\PollBundle\Application\Event`. A listener that needs more reads it from `Polls` with the identifier:

```php
use Phil\PollBundle\Application\Event\VoteCast;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class ForgetCachedResults
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    #[AsEventListener]
    public function onVoteCast(VoteCast $event): void
    {
        $this->cache->delete('poll-results-'.$event->poll);
    }
}
```

An application that sends the event on a Messenger bus sends it as it is: it holds an identifier only.

## In the browser

The results drawn by `@PhilPoll/results.html.twig` dispatch the DOM event `poll:revealed` on the window once the poll
is closed, once per poll in a browser tab. Its `detail` holds `poll`, the identifier, and `winners`, the labels of the
options that won or were elected. See [The voting interface](voting-interface.md#celebrating-revealed-results).
