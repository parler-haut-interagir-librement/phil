# phil/scheduled-transitions-bundle

**Publish a page on Monday at nine, unpublish it a month later, without anyone online.**

phil/scheduled-transitions-bundle plans transitions for a moment in a Symfony application. A transition is a name, such
as `publish`, `unpublish` or `archive`, applied to a resource at a given time. It is kept in Doctrine until it is due,
then applied by a `TransitionHandler` the application writes, or through `symfony/workflow` with its guards and events.
A handler that throws is a failed attempt: the transition stays pending and is tried again at the next run, until its
attempts are spent. Planning the same transition twice for a resource moves it instead of doubling it.

It is for an application where editors prepare content ahead of time: an announcement that goes live at a set hour, a
promotion that ends on its own, a page that is archived after an event. The bundle knows nothing of what publishing
means for a page; the handler or the workflow does.

The bundle runs from Symfony Scheduler, which consumes a message every minute by default, or from
`phil:scheduled-transitions:run` in a cron job when there is no worker. Resources are named through `phil/resource`. The
design keeps the planning, the attempts and the running in the bundle, and leaves the effect of each transition to a
port, so one schedule serves every kind of resource.

## Status

| Part | State |
|---|---|
| `ScheduledTransition`, `TransitionStatus`, the repository port | Implemented and tested |
| `Scheduler`: schedule, move, cancel, run what is due; `TransitionHandler`, `RunReport` | Implemented and tested |
| `DoctrineScheduledTransitionRepository` | Implemented and tested |
| `WorkflowTransitionHandler`, `SubjectResolver` | Implemented and tested |
| Symfony Scheduler: the `phil_transitions` schedule; `phil:scheduled-transitions:run` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering, configuration, the table. |
| [Scheduling](doc/scheduling.md) | Scheduling, moving, cancelling, runs and retries. |
| [Handlers](doc/handlers.md) | A handler of the application's own; the workflow handler. |
| [Running](doc/running.md) | Symfony Scheduler, or the command from cron; runs that overlap; a handler that breaks the entity manager. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type of the bundle. |

## Quick start

```php
$scheduler->schedule(ResourceReference::of('page', $page->id), 'publish', new DateTimeImmutable('2026-11-09 09:00'));
```

```bash
bin/console messenger:consume scheduler_phil_transitions
```

## Requirements

PHP 8.5 or later with `ext-mbstring`; Doctrine ORM 3.7; PSR-20; Symfony 8.2's dependency injection, config and UID;
`phil/resource` 0.1 for resource references. Symfony Scheduler and Messenger, Workflow and Console are optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
