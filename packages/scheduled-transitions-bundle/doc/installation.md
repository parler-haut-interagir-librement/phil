# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `ext-mbstring` | any | An error kept to 255 characters |
| `doctrine/orm` | ^3.7 | The table of transitions |
| `psr/clock` | ^1.0 | What is due; the container's `clock` service, which FrameworkBundle registers once `symfony/clock` is installed |
| `symfony/dependency-injection`, `symfony/config`, `symfony/uid` | ^8.2 | The bundle class, its configuration, identifiers |
| `phil/resource` | ^0.1 | `ResourceReference`, the kind and identifier of a resource |
| `symfony/scheduler`, `symfony/messenger` | optional | Runs from Symfony Scheduler |
| `symfony/workflow` | optional | Transitions applied through a workflow |
| `symfony/console` | optional | `phil:scheduled-transitions:run` |

```sh
composer require phil/scheduled-transitions-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\ScheduledTransitionsBundle\PhilScheduledTransitionsBundle::class => ['all' => true],
];
```

```yaml
# config/packages/phil_scheduled_transitions.yaml
phil_scheduled_transitions:
    frequency: '1 minute'   # how often Symfony Scheduler runs what is due
    batch: 100              # transitions a run applies at most
    max_attempts: 3         # tries before a failing transition is given up
    claim_for: 600          # seconds a run holds a transition it applies
```

With DoctrineBundle, the bundle maps its entity; the application's migrations create `phil_scheduled_transition`:
`id`, `resource_kind`, `resource_id`, `transition` (64), `due_at` (UTC), `status` (`pending`, `done`, `failed`,
`cancelled`), `attempts`, `last_error` (255), `applied_at`, `claimed_until` (UTC, the claim of the run applying it),
indexed on status and moment, and on the resource.
