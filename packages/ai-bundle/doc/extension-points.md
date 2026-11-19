# Extension points

What an application implements, configures, replaces or relies on.

## Ports

### `UserKeyPlatformFactory`

`Phil\AiBundle\Infrastructure\SymfonyAi\UserKeyPlatformFactory` — builds a Symfony AI platform on a person's own key,
named per platform by `user_key_factory`. See [Keys and quotas](keys-and-quotas.md#the-persons-own-key).

| Method | Contract |
|---|---|
| `create(string $apiKey): PlatformInterface` | A platform that calls the provider with that key; keeps the key nowhere else and never logs it |

### `Completions`

`Phil\AiBundle\Application\Port\Completions` — the models. `SymfonyAiCompletions` calls Symfony AI; a test answers in
its place.

| Method | Contract |
|---|---|
| `complete(ModelCall $call, ?string $userKey = null): Completion` | The model's answer — text, structured data, proposed tool calls or media — and the tokens it used, on the person's key when one is given, on the application's otherwise; any failure it throws is journaled by `AiTasks`, given back to the quota and rethrown as `TaskFailed` |
| `stream(ModelCall $call, ?string $userKey = null): Generator<int, string, mixed, Completion>` | The text in pieces as the model writes them, then the whole completion as the generator's return value; a failure while streaming is handled as one of `complete()` |

### `UserKeys`

`Phil\AiBundle\Application\Port\UserKeys` — a person's key for a platform. `UserSecretsKeys` reads it from
`phil/user-secrets-bundle` when that bundle is registered; `NoUserKeys` otherwise.

| Method | Contract |
|---|---|
| `keyFor(string $owner, string $platform, string $purpose): ?string` | The key, or `null` when the person has none for that platform or revoked it; the purpose goes to the audit trail |

### `JournalRepository`

`Phil\AiBundle\Domain\Repository\JournalRepository` — the journal of kind, tokens, cost and subject, which never holds
a prompt or an answer. Doctrine or memory, by `store`. See [The journal](journal.md).

| Method | Contract |
|---|---|
| `get(string $id): ?JournalEntry` | One entry |
| `save(JournalEntry $entry): void` | Keeps it |
| `ofSubject(Subject $subject, DateTimeImmutable $from, DateTimeImmutable $before): list<JournalEntry>` | A subject's entries in a period |
| `totals(Subject $subject, DateTimeImmutable $from, DateTimeImmutable $before): JournalTotals` | What those entries add up to, summed by the store; `JournalTotals::fromGroups()` reads the rows of a grouped query |
| `eraseSubject(Subject $subject): int` | Removes a subject's entries; returns how many |

Each alias is public and points at the application's own service when it redefines it.

## Configuration

| Option | What it extends |
|---|---|
| `platforms` | The Symfony AI platforms, each with its factory on a person's key and the name of that key's secret |
| `tasks` | The kinds of task: platform, model, options, the tokens to reserve, the output, the structure, the tools and the purpose |
| `prices`, `currency` | What each model costs, per token and per call, for the journal and the budgets |
| `key_policy` | Whose key a task runs on |
| `usage_meter`, `quota` | The `phil/usage-meter` service and the meters that count quotas, budgets and the ceiling |
| `translation_domain` | The domain of the prompts |
| `store`, `entity_manager` | Where the journal is kept |

See [Configuration](configuration.md).

## Notices

The notices are translations of the domain `phil_ai_notice` — `interaction`, `generated.text`, `generated.image`,
`generated.speech` —: an application rewords them, or adds a language, in its own translations. See
[Transparency and privacy](transparency.md).

## Privacy module

With `phil/privacy-bundle`, `AiJournalPrivacy` carries the tags `phil_privacy.exporter` and `phil_privacy.eraser`; an
application that keeps more about its tasks registers its own module beside it.

## Prompts

The prompts of a task are translations of `translation_domain`, `phil_ai` by default: an application writes them, in
each language it runs tasks in, as it writes any message — see [Tasks and suggestions](tasks.md).
