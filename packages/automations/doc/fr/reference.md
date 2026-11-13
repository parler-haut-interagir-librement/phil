<!-- phil-dev source-sha256=727d67e036423651ef1a31557488f62488cedcdf5ecd751e4282c2fa4322187d -->
# Référence

Chaque type public de la bibliothèque, avec son rôle.

| Type | Sorte | Rôle |
|---|---|---|
| `Phil\Automations\Definition\AutomationDefinition` | `final readonly class` | Une version d'une automatisation : déclencheur et étapes, bornés |
| `Phil\Automations\Definition\AutomationId` | `final readonly class` | Le nom stable d'une automatisation |
| `Phil\Automations\Definition\Step` | `final readonly class` | Une action, un délai, un filtre ou un embranchement |
| `Phil\Automations\Definition\StepKind` | enum | Les quatre sortes d'étape |
| `Phil\Automations\Definition\BranchPath` | enum | `then` ou `else` |
| `Phil\Automations\Definition\StepPath` | `final readonly class` | L'endroit où se trouve une étape, écrit `2/then/0` |
| `Phil\Automations\Definition\Condition` | `final readonly class` | Le langage et la source d'une condition |
| `Phil\Automations\Definition\TriggerReference` | `final readonly class` | Le type de déclencheur sur lequel démarre une définition, avec ses options |
| `Phil\Automations\Definition\ComponentType` | `final class` | La règle d'un type de composant à points |
| `Phil\Automations\Definition\Options` | `final readonly class` | Les options bornées d'un déclencheur ou d'une action |
| `Phil\Automations\Definition\DefinitionReader` | `final readonly class` | Lit une définition à partir de JSON, strictement |
| `Phil\Automations\Definition\DefinitionWriter` | `final readonly class` | Écrit une définition en JSON canonique |
| `Phil\Automations\Definition\DefinitionValidator` | `final readonly class` | Vérifie une définition contre le catalogue |
| `Phil\Automations\Definition\InMemoryDefinitions` | `final class` | Les définitions en mémoire |
| `Phil\Automations\Catalogue\Catalogue` | `final readonly class` | Les déclencheurs, les actions et les langages de condition enregistrés |
| `Phil\Automations\Catalogue\ComponentDescription` | `final readonly class` | Ce qu'un déclencheur ou une action dit de lui-même |
| `Phil\Automations\Catalogue\OptionSpec` | `final readonly class` | Une option que prend un composant |
| `Phil\Automations\Catalogue\OptionType` | enum | Les sortes de valeur d'option |
| `Phil\Automations\Contract\Trigger` | interface | Transforme un événement en démarrage d'exécutions |
| `Phil\Automations\Contract\Action` | interface | Quelque chose que fait une étape |
| `Phil\Automations\Contract\CompensableAction` | interface | Une action qui peut défaire ce qu'elle a fait |
| `Phil\Automations\Contract\ConditionEvaluator` | interface | Lit les conditions d'un langage |
| `Phil\Automations\Contract\Definitions` | interface | L'endroit où les définitions sont conservées |
| `Phil\Automations\Contract\RunStore` | interface | L'endroit où les exécutions sont conservées |
| `Phil\Automations\Contract\WebhookKeys` | interface | Les clés de signature de l'action de webhook, par nom |
| `Phil\Automations\Engine\AutomationEngine` | `final readonly class` | Démarre des exécutions sur des événements et les fait parcourir leurs étapes |
| `Phil\Automations\Engine\TriggerMatch` | `final readonly class` | Ce qu'un déclencheur dit d'un événement correspondant |
| `Phil\Automations\Engine\ActionContext` | `final readonly class` | Ce que reçoit une action pour une étape d'une exécution |
| `Phil\Automations\Engine\Payload` | `final readonly class` | Des identifiants seulement : la charge utile d'un déclencheur, le résultat d'une action |
| `Phil\Automations\Run\AutomationRun` | `final readonly class` | Une exécution d'une version, pour un événement |
| `Phil\Automations\Run\RunId` | `final readonly class` | L'identifiant d'une exécution, tiré de l'automatisation et de la clé d'événement |
| `Phil\Automations\Run\RunState` | enum | Où en est une exécution |
| `Phil\Automations\Run\CompletedStep` | `final readonly class` | Une étape d'action faite, avec son résultat |
| `Phil\Automations\Run\InMemoryRunStore` | `final class` | Les exécutions en mémoire |
| `Phil\Automations\Expression\ExpressionConditions` | `final readonly class` | Des conditions dans l'ExpressionLanguage de Symfony |
| `Phil\Automations\Expression\SafeExpressionLanguage` | `final class` | ExpressionLanguage sans fonctions |
| `Phil\Automations\Messenger\AdvanceRun` | `final readonly class` | Le message qui fait avancer une exécution |
| `Phil\Automations\Messenger\AdvanceRunHandler` | `final readonly class` | Son *handler* |
| `Phil\Automations\Messenger\ResumeDueRuns` | `final readonly class` | Le message qui reprend les exécutions dues |
| `Phil\Automations\Messenger\ResumeDueRunsHandler` | `final readonly class` | Son *handler* |
| `Phil\Automations\Messenger\AutomationsSchedule` | `final readonly class` | La planification `automations` de Symfony Scheduler |
| `Phil\Automations\Webhook\WebhookAction` | `final readonly class` | L'action `webhook` |
| `Phil\Automations\Dbal\DbalDefinitions` | `final readonly class` | Les définitions dans une table DBAL |
| `Phil\Automations\Dbal\DbalRunStore` | `final readonly class` | Les exécutions dans une table DBAL |
| `Phil\Automations\Dbal\Sql` | `final class` | Ce que les magasins DBAL partagent |
| `Phil\Automations\Attribute\AsAutomationTrigger` | attribute | Marque un déclencheur, étiquette `phil.automations.trigger` |
| `Phil\Automations\Attribute\AsAutomationAction` | attribute | Marque une action, étiquette `phil.automations.action` |
| `Phil\Automations\Exception\AutomationsException` | interface | Toute exception de la bibliothèque |
| `Phil\Automations\Exception\InvalidDefinition` | `final class` | Une définition ou une partie de définition refusée |
| `Phil\Automations\Exception\InvalidPayload` | `final class` | Une charge utile qui ne contient pas que des identifiants |
| `Phil\Automations\Exception\UnknownComponent` | `final class` | Un déclencheur, une action ou un langage non enregistré |
| `Phil\Automations\Exception\ActionFailed` | `final class` | Une action qui n'a pas pu faire son travail, à réessayer ou non |
