# Configuration

```yaml
phil_ai:
    platforms:
        main:
            platform: 'app.ai_platform'            # required: a Symfony AI PlatformInterface service
            user_key_factory: 'app.ai_user_platform' # a UserKeyPlatformFactory service, for the person's own key
            secret: 'main-key'                     # the person's secret in phil/user-secrets-bundle
    currency: USD
    prices:
        the-model: { input: 3000000, output: 15000000 }
        the-image-model: { input: 0, output: 0, per_call: 3000 }
    tasks:
        draft.rewrite:
            platform: main
            model: 'the-model'
            options: { max_tokens: 1024, temperature: 0.7 }
            reserve: 2000
            output: text                           # text, structured, image or speech
            structure: ~                           # the class of a structured output
            tools:
                look_up: { description: 'Looks a rule up.', parameters: { type: object } }
            purpose: 'Proposes a rewrite of a passage the author chose.'
    key_policy: prefer_user
    usage_meter: 'Phil\UsageMeter\UsageMeter'
    quota:
        tokens_meter: ai_tokens
        tasks_meter: ai_tasks
        count_user_keys: false
        budget_meter: ai_budget
        ceiling_meter: ai_ceiling
        ceiling_subject: phil_ai.application
        concurrency: ~                             # how many tasks of one subject run at once, through symfony/semaphore
        semaphore_factory: semaphore.factory
    translation_domain: phil_ai
    store: doctrine
    entity_manager: default
```

| Option | Default | Meaning |
|---|---|---|
| `platforms` | none | The platforms by name: up to 64 lowercase letters, digits, `_` or `-`, starting with a letter. |
| `platforms.*.platform` | required | The service of the platform on the application's key. |
| `platforms.*.user_key_factory` | `null` | The service that builds the platform on a person's own key; without it, the platform takes no key of a person. |
| `platforms.*.secret` | `null` | The name of the secret, in `phil/user-secrets-bundle`, that holds a person's key for this platform. Needs `user_key_factory`. |
| `currency` | `USD` | The ISO 4217 code of the prices and of the journal's costs. |
| `prices` | none | Per model, `input` and `output` in micros of the currency per million tokens: 3.00 is `3000000`; `per_call`, what each call costs whatever its tokens, 0 by default. A model without a price costs 0. |
| `tasks` | none | Per kind, the stem of its prompts: the platform, among `platforms`; the model; the options passed as they are to the platform; and `reserve`, the tokens the task reserves on the subject's quota before it starts, 1 or more, 1 by default; with the option `max_tokens`, it makes the task's cost bound — see [Keys and quotas](keys-and-quotas.md#the-cost-bound). |
| `tasks.*.output` | `text` | What the task asks for: `text`, `structured`, `image` or `speech`. See [Outputs, streaming and tools](outputs.md). |
| `tasks.*.structure` | `null` | The class a structured output is read into; required for `structured`, refused otherwise. |
| `tasks.*.tools` | none | The tools the model may propose, by name — 1 to 64 letters, digits, `_` or `-` —, each with its `description` and the JSON schema of its `parameters`. Never run by the bundle; refused for images and speech. |
| `tasks.*.purpose` | `''` | What the task is for, in a sentence, for the usage register. |
| `key_policy` | `prefer_user` | `application`, `prefer_user` or `require_user` — see [Keys and quotas](keys-and-quotas.md). `require_user` needs `phil/user-secrets-bundle`. |
| `usage_meter` | `null` | The `phil/usage-meter` service that counts the quotas. Required as soon as a quota meter is named. |
| `quota.tokens_meter` | `null` | The meter of tokens: each task's `reserve` consumed before it starts, settled with the tokens spent. |
| `quota.tasks_meter` | `null` | The meter of tasks: one consumed per task. |
| `quota.count_user_keys` | `false` | Whether tasks run on a person's own key count in the quotas. |
| `quota.budget_meter` | `null` | The meter of the subject's spending, in micros of the currency: each task's cost bound consumed before it starts, settled with its cost. |
| `quota.ceiling_meter` | `null` | The meter of the whole application's spending, in micros of the currency, reserved and settled like the budget. |
| `quota.ceiling_subject` | `phil_ai.application` | The subject the ceiling is counted on. |
| `quota.concurrency` | `null` | How many tasks of one subject run at once, 1 or more; `null` for no bound. Needs `usage_meter` and `symfony/semaphore`. |
| `quota.semaphore_factory` | `semaphore.factory` | The `symfony/semaphore` factory service of `quota.concurrency`. |
| `translation_domain` | `phil_ai` | The translation domain of the prompts. |
| `store` | `doctrine` | Where the journal is kept: `doctrine`, or `memory` for tests. |
| `entity_manager` | `default` | The Doctrine entity manager of the journal. |

The configuration is checked when the container is built: a task on a platform that is not declared, a secret without a
factory, a quota, budget or ceiling meter or a concurrency without a usage meter, a concurrency below 1, a structure
without `structured` or the reverse, a tool on an image or speech task, and `require_user` without
`phil/user-secrets-bundle` are refused with Symfony's `InvalidConfigurationException`; a concurrency whose semaphore
factory is not a service fails when the container is compiled.

## Services

| Service | Public | Role |
|---|---|---|
| `Phil\AiBundle\Application\AiTasks` | yes | Runs tasks and records decisions |
| `Phil\AiBundle\Domain\Repository\JournalRepository` | yes (alias) | The journal |
| `Phil\AiBundle\Application\Port\Completions` | yes (alias) | The models, through Symfony AI; a test replaces it |
| `Phil\AiBundle\Application\Port\UserKeys` | no (alias) | The person's keys, or none |
| `Phil\AiBundle\Application\AiNotice` | yes | The notices that a person talks to an AI, and that what they see was generated |
| `Phil\AiBundle\Application\AiUsageRegister` | yes | The application's uses of AI, from its tasks |
| `Phil\AiBundle\Infrastructure\Privacy\AiJournalPrivacy` | no | The journal's module of `phil/privacy-bundle`, when it is installed |
| `Phil\AiBundle\Infrastructure\Replicate\ReplicatePlatformFactory` | no | The Replicate image platform on a token, when an HTTP client is installed |
