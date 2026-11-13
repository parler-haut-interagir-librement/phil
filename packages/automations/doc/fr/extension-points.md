<!-- phil-dev source-sha256=e8ed2f8da6bba3cdc051c5ed5a17fa04634f1391c11fb52c063f60076b5db121 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

La bibliothèque ne déclare aucun service. Ses ports sont des interfaces sous `Phil\Automations\Contract` ; chacun a une
implémentation fournie, ou revient à l'application ou à un paquet d'écrire.

| Port | Contrat | Fourni |
|---|---|---|
| `Trigger` | `describe()`, `events()`, `match(object $event, Options $options): ?TriggerMatch` ; des identifiants seulement dans la correspondance ; aucun état entre les appels | — |
| `Action` | `describe()`, `execute(ActionContext $context): Payload` ; idempotente sous `ActionContext::$idempotencyKey` ; lève `ActionFailed` | `WebhookAction` |
| `CompensableAction` | `compensate(ActionContext $context, Payload $result): void` ; défait ce qu'`execute()` a fait, idempotente aussi | — |
| `ConditionEvaluator` | `language()`, `check(string $source)` qui lève `InvalidDefinition`, `evaluate(string $source, array $variables): bool` | `ExpressionConditions` |
| `Definitions` | `enabledFor()`, `get()`, `publish()`, `disable()` ; une version une fois conservée ne change jamais | `InMemoryDefinitions`, `DbalDefinitions` |
| `RunStore` | `create()` une fois par identifiant, `save()` seulement sur la révision lue, `due()`, `purge()` | `InMemoryRunStore`, `DbalRunStore` |
| `WebhookKeys` | `signingKeys(string $name): list<SigningKey>`, la plus récente d'abord, aucune pour un nom inconnu | — |

Les attributs `AsAutomationTrigger` et `AsAutomationAction` nomment les étiquettes `phil.automations.trigger` et
`phil.automations.action`, pour un conteneur qui les transforme en étiquettes.

## Arguments

| Classe | Argument | Valeur par défaut |
|---|---|---|
| `AutomationEngine` | `$leaseSeconds`, la durée pendant laquelle un *worker* détient une exécution | 300 |
| `AutomationEngine` | `$logger`, un journaliseur PSR-3 | aucun |
| `AutomationsSchedule` | `$frequency`, `$limit` des exécutions reprises | `1 minute`, 500 |
| `WebhookAction` | `$timeout` en secondes, `$allowPrivateNetworks` | 10, `false` |
| `ExpressionConditions` | `$language`, un `ExpressionLanguage` de l'application | `SafeExpressionLanguage`, sans fonctions |
| `DbalDefinitions`, `DbalRunStore` | `$table` | `phil_automation_definition`, `phil_automation_run` |

Un `ExpressionLanguage` donné à `ExpressionConditions` apporte ses fonctions avec lui : ne donnez que des fonctions qui
ne lisent rien en dehors de leurs arguments.
