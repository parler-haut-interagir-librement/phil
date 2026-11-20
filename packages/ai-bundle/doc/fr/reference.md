<!-- phil-dev source-sha256=72565a9df0c1201d1cfba14b10d91b25d8f77ed212f0dbcb0a30da12c9325436 -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `Phil\AiBundle\PhilAiBundle` | bundle | La configuration `phil_ai`, l'association Doctrine |
| `Phil\AiBundle\Domain\ValueObject\TaskKind` | `final readonly class` | Ce que fait une tâche, la racine de ses invites |
| `Phil\AiBundle\Domain\ValueObject\Subject` | `final readonly class` | Le sujet à qui une tâche est facturée |
| `Phil\AiBundle\Domain\ValueObject\Tokens` | `final readonly class` | Les jetons lus et écrits |
| `Phil\AiBundle\Domain\ValueObject\Cost` | `final readonly class` | Des millionièmes d'une devise |
| `Phil\AiBundle\Domain\ValueObject\Price` | `final readonly class` | Des millionièmes par million de jetons lus et écrits, et par appel |
| `Phil\AiBundle\Domain\Journal\KeySource` | enum | `application`, `user` |
| `Phil\AiBundle\Domain\Journal\Outcome` | enum | `succeeded`, `failed` |
| `Phil\AiBundle\Domain\Journal\Decision` | enum | `pending`, `accepted`, `edited`, `rejected` |
| `Phil\AiBundle\Domain\Journal\Output` | enum | `text`, `structured`, `image`, `speech` |
| `Phil\AiBundle\Domain\Journal\JournalTotals` | `final readonly class` | Le total des tâches d'un sujet |
| `Phil\AiBundle\Domain\Model\JournalEntry` | entité | La ligne d'une tâche |
| `Phil\AiBundle\Domain\Repository\JournalRepository` | interface | Le journal |
| `Phil\AiBundle\Domain\Exception\AiException` | interface | Toutes les exceptions du bundle |
| `Phil\AiBundle\Domain\Exception\InvalidAiValue` | `final class` | Une valeur mal formée |
| `Phil\AiBundle\Domain\Exception\AlreadyDecided` | `final class` | Une suggestion décidée deux fois, ou une tâche échouée décidée |
| `Phil\AiBundle\Application\AiTasks` | `final readonly class` | L'exécution et le flux continu des tâches, les décisions, les totaux, l'effacement |
| `Phil\AiBundle\Application\SuggestionStream` | `final class` | La réponse d'une tâche par morceaux, puis sa suggestion |
| `Phil\AiBundle\Application\ToolDefinition` | `final readonly class` | Un outil que le modèle peut proposer d'appeler |
| `Phil\AiBundle\Application\ProposedToolCall` | `final readonly class` | Un appel que le modèle a proposé, non exécuté |
| `Phil\AiBundle\Application\Media` | `final readonly class` | Une image ou une parole générée |
| `Phil\AiBundle\Application\AiNotice` | `final readonly class` | Les avis à côté d'un modèle |
| `Phil\AiBundle\Application\AiUsageRegister` | `final readonly class` | Les usages de l'IA de l'application |
| `Phil\AiBundle\Application\AiUse` | `final readonly class` | Un usage du registre |
| `Phil\AiBundle\Application\TaskRequest` | `final readonly class` | Une tâche à exécuter |
| `Phil\AiBundle\Application\Suggestion` | `final readonly class` | Ce que propose une tâche |
| `Phil\AiBundle\Application\TaskDefinition` | `final readonly class` | La façon dont une sorte s'exécute |
| `Phil\AiBundle\Application\TaskCatalog` | `final readonly class` | Les définitions par sorte |
| `Phil\AiBundle\Application\Prompt` | `final readonly class` | Les invites système et utilisateur d'une tâche |
| `Phil\AiBundle\Application\PromptTemplates` | `final readonly class` | Les invites tirées du traducteur |
| `Phil\AiBundle\Application\ModelCall` | `final readonly class` | Un appel à un modèle |
| `Phil\AiBundle\Application\Completion` | `final readonly class` | La réponse du modèle et les jetons |
| `Phil\AiBundle\Application\PriceList` | `final readonly class` | Les prix par modèle, dans une seule devise |
| `Phil\AiBundle\Application\Quota` | `final readonly class` | Les compteurs de jetons, de tâches, de budget et de plafond de `phil/usage-meter` |
| `Phil\AiBundle\Application\KeyPolicy` | enum | `application`, `prefer_user`, `require_user` |
| `Phil\AiBundle\Application\NoUserKeys` | `final readonly class` | Personne ne prête de clé |
| `Phil\AiBundle\Application\Port\Completions` | interface | Les modèles |
| `Phil\AiBundle\Application\Port\UserKeys` | interface | Les clés de la personne |
| `Phil\AiBundle\Application\Exception\UnknownTask` | `final class` | Une sorte non définie |
| `Phil\AiBundle\Application\Exception\MissingPrompt` | `final class` | Une invite utilisateur sans traduction |
| `Phil\AiBundle\Application\Exception\NoUserKey` | `final class` | Une tâche qui exige la clé de la personne, qui n'en a aucune |
| `Phil\AiBundle\Application\Exception\TaskFailed` | `final class` | Un appel au modèle échoué |
| `Phil\AiBundle\Application\Exception\UnknownSuggestion` | `final class` | Une décision sur une tâche inconnue |
| `Phil\AiBundle\Application\Exception\UnknownPlatform` | `final class` | Une plateforme non déclarée, ou sans fabrique pour la clé de la personne |
| `Phil\AiBundle\Infrastructure\SymfonyAi\SymfonyAiCompletions` | `final readonly class` | Les modèles par Symfony AI |
| `Phil\AiBundle\Infrastructure\SymfonyAi\UserKeyPlatformFactory` | interface | Une plateforme avec la clé de la personne |
| `Phil\AiBundle\Infrastructure\Replicate\ReplicatePlatformFactory` | `final readonly class` | Une plateforme Symfony AI de modèles d'images Replicate |
| `Phil\AiBundle\Infrastructure\Replicate\ReplicateImageClient` | `final readonly class` | Crée une prédiction Replicate et l'attend |
| `Phil\AiBundle\Infrastructure\Replicate\ReplicateImageConverter` | `final readonly class` | Va chercher l'image d'une prédiction sur le réseau de diffusion de Replicate |
| `Phil\AiBundle\Infrastructure\Privacy\AiJournalPrivacy` | `final readonly class` | Le module du journal de `phil/privacy-bundle` |
| `Phil\AiBundle\Infrastructure\UserSecrets\UserSecretsKeys` | `final readonly class` | Les clés de la personne dans `phil/user-secrets-bundle` |
| `Phil\AiBundle\Infrastructure\Doctrine\DoctrineJournalRepository` | `final readonly class` | Le journal dans Doctrine |
| `Phil\AiBundle\Infrastructure\Memory\InMemoryJournalRepository` | `final class` | Le journal dans un tableau |
| `Phil\AiBundle\Infrastructure\DependencyInjection\AiServices` | `final class` | Le câblage des services |
