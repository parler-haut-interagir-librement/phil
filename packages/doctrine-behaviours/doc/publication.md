# Publication

| Type | Kind | Role |
|---|---|---|
| `Contract\Publishable` | interface | The state in the workflow's own accessors, `isPublished()`, the dates |
| `PublishableEntity` | trait | The columns `publication_state`, 32 characters, `published_at` and `unpublished_at` |
| `PublicationWorkflow` | constants, `definition()`, `markingStore()` | The state machine, under the name `phil_publication` |
| `PublicationListener` | listener: `workflow.<name>.entered` | Stamps the dates, in UTC |

The state is kept by `symfony/workflow`, an optional dependency, so a guard or a listener of the application can hold
or follow a publication like any other transition.

| Transition | From | To |
|---|---|---|
| `publish` | `draft`, `unpublished` | `published` |
| `unpublish` | `published` | `unpublished` |

| Entering | `published_at` | `unpublished_at` |
|---|---|---|
| `published` | set to now, unless the record already has one | forgotten |
| `unpublished` | kept | set to now |

The date of the first publication stays when a record is withdrawn and published again. A record that was given a date
beforehand, by an import or an editor choosing the date shown, keeps it. Flushing stays the application's, as with every
workflow.

Outside Symfony:

```php
$dispatcher->addListener('workflow.'.PublicationWorkflow::NAME.'.entered', new PublicationListener($clock));
$workflow = new StateMachine(PublicationWorkflow::definition(), PublicationWorkflow::markingStore(), $dispatcher, PublicationWorkflow::NAME);

$workflow->apply($article, PublicationWorkflow::PUBLISH);
$manager->flush();
```

## Publishing at a set time

A date in the future is a job for a scheduler, not a column: the state changes when the time comes, through the
workflow, with its guards. `phil/scheduled-transitions-bundle` does that for any object `workflow.registry` knows, and
reaches this behaviour with the transition names alone, so neither package depends on the other. The application writes
the `SubjectResolver` that finds its records:

```php
$scheduler->schedule(ResourceReference::of('article', $article->id), 'publish', new DateTimeImmutable('2026-11-09 09:00'));
```

A workflow with more places, such as a review before publication, keeps these three names for the listener to stamp the
dates; the bundle then leaves its declaration to the application.
