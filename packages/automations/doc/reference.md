# Reference

Every public type of the library, with its role.

| Type | Kind | Role |
|---|---|---|
| `Phil\Automations\Definition\AutomationDefinition` | `final readonly class` | One version of an automation: trigger and steps, bounded |
| `Phil\Automations\Definition\AutomationId` | `final readonly class` | The stable name of an automation |
| `Phil\Automations\Definition\Step` | `final readonly class` | An action, a delay, a filter or a branch |
| `Phil\Automations\Definition\StepKind` | enum | The four kinds of step |
| `Phil\Automations\Definition\BranchPath` | enum | `then` or `else` |
| `Phil\Automations\Definition\StepPath` | `final readonly class` | Where a step sits, written `2/then/0` |
| `Phil\Automations\Definition\Condition` | `final readonly class` | A condition's language and source |
| `Phil\Automations\Definition\TriggerReference` | `final readonly class` | The trigger type a definition starts on, with its options |
| `Phil\Automations\Definition\ComponentType` | `final class` | The rule of a dotted component type |
| `Phil\Automations\Definition\Options` | `final readonly class` | The bounded options of a trigger or an action |
| `Phil\Automations\Definition\DefinitionReader` | `final readonly class` | Reads a definition from JSON, strictly |
| `Phil\Automations\Definition\DefinitionWriter` | `final readonly class` | Writes a definition as canonical JSON |
| `Phil\Automations\Definition\DefinitionValidator` | `final readonly class` | Checks a definition against the catalogue |
| `Phil\Automations\Definition\InMemoryDefinitions` | `final class` | The definitions in memory |
| `Phil\Automations\Catalogue\Catalogue` | `final readonly class` | The registered triggers, actions and condition languages |
| `Phil\Automations\Catalogue\ComponentDescription` | `final readonly class` | What a trigger or an action says of itself |
| `Phil\Automations\Catalogue\OptionSpec` | `final readonly class` | One option a component takes |
| `Phil\Automations\Catalogue\OptionType` | enum | The kinds of option value |
| `Phil\Automations\Contract\Trigger` | interface | Turns an event into the start of runs |
| `Phil\Automations\Contract\Action` | interface | Something a step does |
| `Phil\Automations\Contract\CompensableAction` | interface | An action that can undo what it did |
| `Phil\Automations\Contract\ConditionEvaluator` | interface | Reads conditions of one language |
| `Phil\Automations\Contract\Definitions` | interface | Where the definitions are kept |
| `Phil\Automations\Contract\RunStore` | interface | Where the runs are kept |
| `Phil\Automations\Contract\WebhookKeys` | interface | The signing keys of the webhook action, by name |
| `Phil\Automations\Engine\AutomationEngine` | `final readonly class` | Starts runs on events and takes them through their steps |
| `Phil\Automations\Engine\TriggerMatch` | `final readonly class` | What a trigger says of a matching event |
| `Phil\Automations\Engine\ActionContext` | `final readonly class` | What an action is given for one step of one run |
| `Phil\Automations\Engine\Payload` | `final readonly class` | Identifiers only: a trigger's payload, an action's result |
| `Phil\Automations\Run\AutomationRun` | `final readonly class` | One run of one version, for one event |
| `Phil\Automations\Run\RunId` | `final readonly class` | A run's id, drawn from the automation and the event key |
| `Phil\Automations\Run\RunState` | enum | Where a run stands |
| `Phil\Automations\Run\CompletedStep` | `final readonly class` | An action step done, with its result |
| `Phil\Automations\Run\InMemoryRunStore` | `final class` | The runs in memory |
| `Phil\Automations\Expression\ExpressionConditions` | `final readonly class` | Conditions in Symfony's ExpressionLanguage |
| `Phil\Automations\Expression\SafeExpressionLanguage` | `final class` | ExpressionLanguage without functions |
| `Phil\Automations\Messenger\AdvanceRun` | `final readonly class` | The message that takes a run on |
| `Phil\Automations\Messenger\AdvanceRunHandler` | `final readonly class` | Its handler |
| `Phil\Automations\Messenger\ResumeDueRuns` | `final readonly class` | The message that resumes the due runs |
| `Phil\Automations\Messenger\ResumeDueRunsHandler` | `final readonly class` | Its handler |
| `Phil\Automations\Messenger\AutomationsSchedule` | `final readonly class` | The schedule `automations` of Symfony Scheduler |
| `Phil\Automations\Webhook\WebhookAction` | `final readonly class` | The action `webhook` |
| `Phil\Automations\Dbal\DbalDefinitions` | `final readonly class` | The definitions in a DBAL table |
| `Phil\Automations\Dbal\DbalRunStore` | `final readonly class` | The runs in a DBAL table |
| `Phil\Automations\Dbal\Sql` | `final class` | What the DBAL stores share |
| `Phil\Automations\Attribute\AsAutomationTrigger` | attribute | Marks a trigger, tag `phil.automations.trigger` |
| `Phil\Automations\Attribute\AsAutomationAction` | attribute | Marks an action, tag `phil.automations.action` |
| `Phil\Automations\Exception\AutomationsException` | interface | Every exception of the library |
| `Phil\Automations\Exception\InvalidDefinition` | `final class` | A definition or part of one refused |
| `Phil\Automations\Exception\InvalidPayload` | `final class` | A payload that does not hold identifiers only |
| `Phil\Automations\Exception\UnknownComponent` | `final class` | A trigger, action or language not registered |
| `Phil\Automations\Exception\ActionFailed` | `final class` | An action that could not do its work, retryable or not |
