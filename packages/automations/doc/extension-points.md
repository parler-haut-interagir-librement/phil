# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

The library declares no service. Its ports are interfaces under `Phil\Automations\Contract`; each has a shipped
implementation or is the application's or a package's to write.

| Port | Contract | Shipped |
|---|---|---|
| `Trigger` | `describe()`, `events()`, `match(object $event, Options $options): ?TriggerMatch`; identifiers only in the match; no state between calls | — |
| `Action` | `describe()`, `execute(ActionContext $context): Payload`; idempotent under `ActionContext::$idempotencyKey`; throws `ActionFailed` | `WebhookAction` |
| `CompensableAction` | `compensate(ActionContext $context, Payload $result): void`; undoes what `execute()` did, idempotent too | — |
| `ConditionEvaluator` | `language()`, `check(string $source)` throwing `InvalidDefinition`, `evaluate(string $source, array $variables): bool` | `ExpressionConditions` |
| `Definitions` | `enabledFor()`, `get()`, `publish()`, `disable()`; a version once kept never changes | `InMemoryDefinitions`, `DbalDefinitions` |
| `RunStore` | `create()` once per id, `save()` only over the revision read, `due()`, `purge()` | `InMemoryRunStore`, `DbalRunStore` |
| `WebhookKeys` | `signingKeys(string $name): list<SigningKey>`, the newest first, none for an unknown name | — |

The attributes `AsAutomationTrigger` and `AsAutomationAction` name the tags `phil.automations.trigger` and
`phil.automations.action`, for a container that turns them into tags.

## Arguments

| Class | Argument | Default |
|---|---|---|
| `AutomationEngine` | `$leaseSeconds`, how long a worker holds a run | 300 |
| `AutomationEngine` | `$logger`, a PSR-3 logger | none |
| `AutomationsSchedule` | `$frequency`, `$limit` of the resumed runs | `1 minute`, 500 |
| `WebhookAction` | `$timeout` in seconds, `$allowPrivateNetworks` | 10, `false` |
| `ExpressionConditions` | `$language`, an `ExpressionLanguage` of the application's | `SafeExpressionLanguage`, without functions |
| `DbalDefinitions`, `DbalRunStore` | `$table` | `phil_automation_definition`, `phil_automation_run` |

An `ExpressionLanguage` given to `ExpressionConditions` brings its functions with it: give only functions that read
nothing outside their arguments.
