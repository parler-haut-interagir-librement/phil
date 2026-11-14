# Events

Every change of the progress dispatches an event after it is saved, through the PSR-14 dispatcher when there is one.
Each carries identifiers only: the user identifier, the flow and, for a step, the step; for an answer, the choices
picked, not what they grant.

| Event | When |
|---|---|
| `StepCompleted` (`user`, `flow`, `step`) | A step is completed: by the person, by an event, by a checker, or by the application. |
| `QuestionAnswered` (`user`, `flow`, `step`, `choices`) | The question of a step is answered, with the identifiers of the choices picked; `StepCompleted` follows. |
| `StepSkipped` (`user`, `flow`, `step`) | A pending step is skipped. |
| `FlowFinished` (`user`, `flow`) | Every step is done, completed or skipped, for the first time since the flow was started. |
| `FlowDismissed` (`user`, `flow`) | The user puts the flow away. |
| `FlowReset` (`user`, `flow`) | The user starts the flow again. |

They are in `Phil\OnboardingBundle\Application\Event`. A listener of `FlowFinished` may award a badge, send a welcome
message or open the next flow:

```php
#[AsEventListener]
final readonly class WelcomeAuthor
{
    public function __invoke(FlowFinished $event): void
    {
        if ('author' === $event->flow) {
            // …
        }
    }
}
```

A Messenger message built from an event carries the same identifiers, and reads the rest when it is handled.
