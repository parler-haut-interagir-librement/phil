# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\AiBundle\PhilAiBundle` | bundle | The configuration `phil_ai`, the Doctrine mapping |
| `Phil\AiBundle\Domain\ValueObject\TaskKind` | `final readonly class` | What a task does, the stem of its prompts |
| `Phil\AiBundle\Domain\ValueObject\Subject` | `final readonly class` | Whom a task is charged to |
| `Phil\AiBundle\Domain\ValueObject\Tokens` | `final readonly class` | Tokens read and written |
| `Phil\AiBundle\Domain\ValueObject\Cost` | `final readonly class` | Micros of a currency |
| `Phil\AiBundle\Domain\ValueObject\Price` | `final readonly class` | Micros per million tokens read and written, and per call |
| `Phil\AiBundle\Domain\Journal\KeySource` | enum | `application`, `user` |
| `Phil\AiBundle\Domain\Journal\Outcome` | enum | `succeeded`, `failed` |
| `Phil\AiBundle\Domain\Journal\Decision` | enum | `pending`, `accepted`, `edited`, `rejected` |
| `Phil\AiBundle\Domain\Journal\Output` | enum | `text`, `structured`, `image`, `speech` |
| `Phil\AiBundle\Domain\Journal\JournalTotals` | `final readonly class` | What a subject's tasks added up to |
| `Phil\AiBundle\Domain\Model\JournalEntry` | entity | The row of a task |
| `Phil\AiBundle\Domain\Repository\JournalRepository` | interface | The journal |
| `Phil\AiBundle\Domain\Exception\AiException` | interface | Every exception of the bundle |
| `Phil\AiBundle\Domain\Exception\InvalidAiValue` | `final class` | A value out of shape |
| `Phil\AiBundle\Domain\Exception\AlreadyDecided` | `final class` | A suggestion decided twice, or a failed task decided |
| `Phil\AiBundle\Application\AiTasks` | `final readonly class` | Running and streaming tasks, deciding, totals, erasure |
| `Phil\AiBundle\Application\SuggestionStream` | `final class` | A task's answer in pieces, then its suggestion |
| `Phil\AiBundle\Application\ToolDefinition` | `final readonly class` | A tool the model may propose to call |
| `Phil\AiBundle\Application\ProposedToolCall` | `final readonly class` | A call the model proposed, not run |
| `Phil\AiBundle\Application\Media` | `final readonly class` | A generated image or speech |
| `Phil\AiBundle\Application\AiNotice` | `final readonly class` | The notices beside a model |
| `Phil\AiBundle\Application\AiUsageRegister` | `final readonly class` | The application's uses of AI |
| `Phil\AiBundle\Application\AiUse` | `final readonly class` | One use of the register |
| `Phil\AiBundle\Application\TaskRequest` | `final readonly class` | One task to run |
| `Phil\AiBundle\Application\Suggestion` | `final readonly class` | What a task proposes |
| `Phil\AiBundle\Application\TaskDefinition` | `final readonly class` | How a kind runs |
| `Phil\AiBundle\Application\TaskCatalog` | `final readonly class` | The definitions by kind |
| `Phil\AiBundle\Application\Prompt` | `final readonly class` | The system and user prompts of a task |
| `Phil\AiBundle\Application\PromptTemplates` | `final readonly class` | The prompts from the translator |
| `Phil\AiBundle\Application\ModelCall` | `final readonly class` | One call to a model |
| `Phil\AiBundle\Application\Completion` | `final readonly class` | The model's answer and tokens |
| `Phil\AiBundle\Application\PriceList` | `final readonly class` | The prices by model, in one currency |
| `Phil\AiBundle\Application\Quota` | `final readonly class` | The meters of tokens, tasks, budget and ceiling of `phil/usage-meter` |
| `Phil\AiBundle\Application\KeyPolicy` | enum | `application`, `prefer_user`, `require_user` |
| `Phil\AiBundle\Application\NoUserKeys` | `final readonly class` | No one lends a key |
| `Phil\AiBundle\Application\Port\Completions` | interface | The models |
| `Phil\AiBundle\Application\Port\UserKeys` | interface | The person's keys |
| `Phil\AiBundle\Application\Exception\UnknownTask` | `final class` | A kind not defined |
| `Phil\AiBundle\Application\Exception\MissingPrompt` | `final class` | A user prompt without translation |
| `Phil\AiBundle\Application\Exception\NoUserKey` | `final class` | A task that needs the person's key, and they have none |
| `Phil\AiBundle\Application\Exception\TaskFailed` | `final class` | A failed model call |
| `Phil\AiBundle\Application\Exception\UnknownSuggestion` | `final class` | A decision on an unknown task |
| `Phil\AiBundle\Application\Exception\UnknownPlatform` | `final class` | A platform not declared, or without a factory for the person's key |
| `Phil\AiBundle\Infrastructure\SymfonyAi\SymfonyAiCompletions` | `final readonly class` | The models through Symfony AI |
| `Phil\AiBundle\Infrastructure\SymfonyAi\UserKeyPlatformFactory` | interface | A platform on the person's key |
| `Phil\AiBundle\Infrastructure\Replicate\ReplicatePlatformFactory` | `final readonly class` | A Symfony AI platform of Replicate image models |
| `Phil\AiBundle\Infrastructure\Replicate\ReplicateImageClient` | `final readonly class` | Creates a Replicate prediction and waits for it |
| `Phil\AiBundle\Infrastructure\Replicate\ReplicateImageConverter` | `final readonly class` | Fetches a prediction's image from Replicate's delivery network |
| `Phil\AiBundle\Infrastructure\Privacy\AiJournalPrivacy` | `final readonly class` | The journal's module of `phil/privacy-bundle` |
| `Phil\AiBundle\Infrastructure\UserSecrets\UserSecretsKeys` | `final readonly class` | The person's keys in `phil/user-secrets-bundle` |
| `Phil\AiBundle\Infrastructure\Doctrine\DoctrineJournalRepository` | `final readonly class` | The journal in Doctrine |
| `Phil\AiBundle\Infrastructure\Memory\InMemoryJournalRepository` | `final class` | The journal in an array |
| `Phil\AiBundle\Infrastructure\DependencyInjection\AiServices` | `final class` | The service wiring |
