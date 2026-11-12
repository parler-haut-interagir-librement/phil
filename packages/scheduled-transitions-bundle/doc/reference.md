# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilScheduledTransitionsBundle` | `final class`, `AbstractBundle` | Alias `phil_scheduled_transitions`; `HANDLER_TAG`, `RESOLVER_TAG` |
| `Domain\Model\ScheduledTransition` | entity | A transition, its moment and its attempts |
| `Domain\Transition\TransitionStatus` | enum | Pending, done, failed, cancelled |
| `Phil\Resource\ResourceReference` | `final readonly class`, from `phil/resource` | A kind and an identifier |
| `Domain\Exception\SchedulerException`, `InvalidSchedule`, `NoHandler` | exceptions | Everything the bundle throws |
| `Domain\Repository\ScheduledTransitionRepository` | interface | The port |
| `Application\Scheduler` | `final readonly class` | Schedule, cancel, run |
| `Application\TransitionHandler` | interface | Applies transitions |
| `Application\RunReport` | `final readonly class` | What a run did |
| `Infrastructure\Doctrine\DoctrineScheduledTransitionRepository` | repository | The port on Doctrine ORM |
| `Infrastructure\Workflow\WorkflowTransitionHandler`, `SubjectResolver`, `Subject` | handler | Through `symfony/workflow` |
| `Infrastructure\Scheduler\TransitionsSchedule`, `RunDueTransitions`, `RunDueTransitionsHandler` | schedule, message | Symfony Scheduler |
| `Infrastructure\Console\RunTransitionsCommand` | console command | `phil:scheduled-transitions:run` |
