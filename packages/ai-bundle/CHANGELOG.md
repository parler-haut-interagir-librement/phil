# Changelog

All notable changes to `phil/ai-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-24

### Added

- `Application\TaskDefinition::maxOutputTokens()`, the task's option `max_tokens`, or its reserve; the cost bound of a
  task, the model's price of its reserve read and of its largest answer written, plus the price per call, reserved on
  the budget and the ceiling.
- The option `phil_ai.quota.concurrency`: how many tasks of one subject run at once, through a semaphore of
  `symfony/semaphore` — suggested, off by default — whose factory `phil_ai.quota.semaphore_factory` names;
  `Application\Quota::enter()` and `Application\Exception\TooManyTasks`.

### Changed

- The development requirements accept `phil/privacy-bundle` 0.3, the minor version of its main branch and no earlier
  one, so that the package installs on its own beside that bundle.
- Requires the latest minor of each dependency (`doctrine/orm` 3.8, `doctrine/collections` 3.1,
  `symfony/http-client-contracts` 3.8, `symfony/translation-contracts` 3.8), and of each development requirement
  (`doctrine/dbal` 4.6, `doctrine/doctrine-bundle` 3.4).

### Security

- The quota reserves each task atomically before the call, through `phil/usage-meter`'s `consume()`: its tokens on the
  meter of tokens, its cost bound on the subject's budget and on the application's ceiling, and one task, all or
  nothing. Tasks started at the same moment no longer all pass a check of the room left and go past the quota, the
  budget or the ceiling together. After the call, the reservation is settled with what was spent — the surplus given
  back, the excess recorded under the key `<id>:adjust` —, and a failed call or a stream left early gives all of it
  back. Code that calls `Quota` itself, rather than through `AiTasks`, passes the cost bound to `admit()` and what it
  reserved to `charge()`: `charge()` without them records everything spent on top of the reservation.

## 0.1.0-alpha - 2026-11-20

### Added

- `Domain\ValueObject\TaskKind`, `Subject`, `Tokens`, `Cost` — micros of an ISO 4217 currency — and `Price` — micros per
  million tokens read and written, rounded up to the micro, and per call —; `InvalidAiValue` for a value out of shape, behind
  `AiException`.
- `Domain\Model\JournalEntry`, the row of the table `phil_ai_journal`: kind, `Output`, subject, platform, model,
  `KeySource`, `Outcome`, tokens, cost, start, duration and `Decision`, never a prompt nor an answer; a suggestion
  decided once, `AlreadyDecided` otherwise. `Domain\Repository\JournalRepository`, the port of the journal: read, save,
  list by subject and span, sum a subject's span, erase a subject.
- `Domain\Journal\JournalTotals`: what a subject's tasks added up to — tasks, failures, tokens, costs by currency,
  decisions —, from entries or from the rows of a grouped query.
- `Application\TaskDefinition` and `TaskCatalog`: the platform, model, options and token reserve of each kind, what it
  asks for — text, structured data of a class, an image, speech —, the `ToolDefinition`s the model may propose to call,
  and its purpose; `UnknownTask`.
- `Application\PromptTemplates`: the `<kind>.system` and `<kind>.user` messages of a translation domain, filled with
  the task's `{placeholders}` in a plain or ICU catalogue; `MissingPrompt`.
- `Application\PriceList`: the cost of each task by model.
- `Application\KeyPolicy` and the port `UserKeys`, with `NoUserKeys`: the application's key, the person's own key when
  they have one, or only theirs; `NoUserKey`.
- `Application\Quota`: a meter of tokens of `phil/usage-meter` checked against the task's reserve and charged with the
  tokens spent, a meter of tasks consumed before each and given back when the call fails, a budget of cost per subject
  and a ceiling for the whole application, the person's own key left out unless configured.
- `Application\AiTasks`: a `TaskRequest` run into a `Suggestion` through the port `Completions`, or streamed into a
  `SuggestionStream` read once, journaled on success and on failure — `TaskFailed`, without the provider's message —;
  `accept()`, `acceptEdited()`, `reject()` by the suggestion's identifier, `UnknownSuggestion`; `totals()` and
  `erase()` of a subject.
- `Application\Suggestion`: the text, structured data, `ProposedToolCall`s never run, or generated `Media`, and
  `disclosure()`, how the work that takes it was made, as a `CreationDisclosure` of `phil/creation-disclosure`.
- `Application\AiNotice`: the notices that a person talks to an AI and that what they see was generated, in the
  translation domain `phil_ai_notice`, shipped in English and French.
- `Application\AiUsageRegister` and `AiUse`: the application's uses of AI, read from its tasks.
- Doctrine and in-memory journals, the Doctrine totals summed by one grouped query.
- The configuration `phil_ai`: platforms, currency, prices per token and per call, tasks with their output, structure,
  tools and purpose, key policy, usage meter, quota, budget and ceiling meters, translation domain, store, entity
  manager; the Doctrine mapping prepended; the secrets adapter wired when
  `phil/user-secrets-bundle` is registered.
- `Infrastructure\SymfonyAi\SymfonyAiCompletions`: the prompt as system and user messages to a Symfony AI platform, or
  the user prompt alone for an image or speech, whole or streamed, structured output through `response_format`, tools
  as Symfony AI tools, the tokens from its `token_usage` metadata; `UserKeyPlatformFactory` builds a platform on the
  person's key; `UnknownPlatform`.
- `Infrastructure\Replicate\ReplicatePlatformFactory`, `ReplicateImageClient` and `ReplicateImageConverter`: a Symfony
  AI platform of Replicate image models, the image fetched from Replicate's delivery network only, within a size
  bound.
- `Infrastructure\Privacy\AiJournalPrivacy`: the journal's module of `phil/privacy-bundle`, export and erasure of a
  subject's rows.
- `Infrastructure\UserSecrets\UserSecretsKeys`: the person's key read from `phil/user-secrets-bundle` by a task of
  theirs, a missing or revoked secret being no key.
- The Flex recipe in `recipe/0.1`: the bundle registered and `config/packages/phil_ai.yaml` with the currency of the
  costs, the platforms and tasks left for the application to declare.
- The documentation in `doc/`, one file per topic, indexed by the README.
