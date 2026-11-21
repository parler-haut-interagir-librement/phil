# Keys and quotas

## Whose key

A task of a person — a `TaskRequest` with an `owner` — runs on a key chosen by `key_policy`:

| Policy | Behaviour |
|---|---|
| `application` | Always the application's key; the person's keys are never read. |
| `prefer_user` | The person's own key when they have one for the platform, the application's otherwise. |
| `require_user` | Only the person's own key: without one, or for a task without owner, `NoUserKey`. |

A task without owner always runs on the application's key, except under `require_user`.

## The person's own key

The person's keys live in `phil/user-secrets-bundle`, sealed at rest, read only by a task run for them. Register that
bundle, and name for each platform the secret that holds a person's key and the factory that builds the platform on
it:

```yaml
phil_ai:
    platforms:
        main:
            platform: 'app.ai_platform'
            user_key_factory: 'App\Ai\MainPlatformOnUserKey'
            secret: 'main-key'
```

```php
use Phil\AiBundle\Infrastructure\SymfonyAi\UserKeyPlatformFactory;
use Symfony\AI\Platform\Bridge\TheProvider\PlatformFactory;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class MainPlatformOnUserKey implements UserKeyPlatformFactory
{
    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    public function create(#[\SensitiveParameter] string $apiKey): PlatformInterface
    {
        return PlatformFactory::create($apiKey, $this->httpClient);
    }
}
```

The person stores their key once, through the secrets bundle:

```php
$secrets->store(Owner::fromString('member-7'), SecretName::fromString('main-key'), ServiceName::fromString('main'), ApiKey::of($submittedKey));
```

Each task reads it with the purpose `AI task "<kind>"`, which goes to the audit trail of the secrets. A missing or
revoked secret is no key; a secret that does not open, or holds an OAuth token, fails with the secrets bundle's
exception. The key is never logged, journaled nor kept by the bundle.

## Quotas

Quotas are counted by `phil/usage-meter`, per subject and per period, by a `UsageMeter` service the application builds
— its meters, periods, limits and store are the application's:

```yaml
services:
    app.ai_meters:
        class: Phil\UsageMeter\Meters
        arguments:
            - !service { class: Phil\UsageMeter\Meter, arguments: ['ai_tokens', !service { class: Phil\UsageMeter\Period\CalendarPeriod, factory: ['Phil\UsageMeter\Period\CalendarPeriod', 'month'] }, !service { class: Phil\UsageMeter\Limit, factory: ['Phil\UsageMeter\Limit', 'of'], arguments: [200000] }] }
    Phil\UsageMeter\UsageMeter:
        arguments: ['@app.ai_meters', '@Phil\UsageMeter\Dbal\DbalUsageStore', '@clock']

phil_ai:
    usage_meter: 'Phil\UsageMeter\UsageMeter'
    quota:
        tokens_meter: ai_tokens
```

- **The meter of tokens** holds the task's `reserve`, its usual size, from before the call until it is settled.
- **The meter of tasks** counts one task per task.
- **The budget** is a meter of the subject's spending, in micros of the price list's currency. It holds the task's
  cost bound until it is settled. A paid offer raises the limit; a free tier may have none at all and rely on the
  meter of tokens.
- **The ceiling** is the same meter of spending for the whole application, counted on one subject of its own,
  `ceiling_subject`, `phil_ai.application` by default: once the month's spending reaches it, every task is refused
  until the period ends or the limit is raised. Every task writes to that one counter, so it is a point of contention
  under heavy load; a budget per subject spreads the writes.
- **The person's own key** is not counted unless `count_user_keys` is true: they pay their provider. Such a task
  reserves nothing, settles nothing and takes no place among the tasks running at once.

```yaml
phil_ai:
    usage_meter: 'Phil\UsageMeter\UsageMeter'
    quota:
        tokens_meter: ai_tokens
        tasks_meter: ai_tasks
        budget_meter: ai_budget      # a meter of micros per subject: 5000000 is 5.00 a month
        ceiling_meter: ai_ceiling    # a meter of micros for the application
```

### The reservation

Before the call, `AiTasks` reserves the task on every meter configured, in this fixed order:

1. its `reserve` of tokens, on the meter of tokens;
2. its cost bound, on the subject's budget;
3. the same cost bound, on the ceiling's subject;
4. one task, on the meter of tasks.

Each step is `phil/usage-meter`'s `consume()`, atomic, the task's identifier as idempotency key. The reservation is
all or nothing: when a step does not fit, the steps already taken are given back by `refund()`, then
`QuotaExceeded` is thrown and nothing is called. Tasks started at the same moment never all pass on the same room:
the database counts each increment against the limit, and refuses the ones that do not fit.

### The cost bound

The cost bound is what the task should cost at most: the model's price, from the price list, applied to the task's
`reserve` as tokens read and to its largest answer as tokens written, plus the model's price per call. The largest
answer is the task's option `max_tokens` when it is a positive integer, and its `reserve` otherwise. With
`reserve: 2000`, `max_tokens: 1024` and a model at 3.00 per million tokens read and 15.00 per million written, the
bound is 2000 × 3 + 1024 × 15 = 21 360 micros, 0.02136, rounded up to the micro.

A model the price list does not price has a bound of zero: the budget and the ceiling then only need room left.

### The settlement

After the call, each of the tokens, the budget and the ceiling is settled with what the task spent:

- when the task spent less than it reserved, the difference is given back by `refund()` under the task's key;
- when it spent more, the difference is recorded by `record()` under a second key, `<task id>:adjust`. A task larger
  than its reserve, or dearer than its bound, may so take the subject past its limit, and the next task is refused
  until the period frees room.

Both steps are idempotent: settling the same task twice counts nothing more. The meter of tasks keeps its one task.

### Failures

When the call fails, or a stream is left before its end or never read, the whole reservation is given back: the
task, its tokens and its cost bound on the budget and the ceiling. A failure costs the subject nothing, and a second
release gives nothing more back.

### Tasks running at once

The reservation bounds what a subject may spend; `quota.concurrency` also bounds how many of its tasks run at once,
so that the spending past the bounds — tasks larger than their reserve — stays within that many tasks:

```yaml
framework:
    semaphore: 'lock://default'

phil_ai:
    usage_meter: 'Phil\UsageMeter\UsageMeter'
    quota:
        budget_meter: ai_budget
        concurrency: 3
```

It needs `symfony/semaphore`, which the bundle suggests and does not require; it is off by default. Each subject has
its own semaphore, `phil_ai.concurrency.` followed by the XXH128 hash of the subject; `quota.semaphore_factory` names
the factory, FrameworkBundle's `semaphore.factory` by default. The place is taken before the reservation and given back
when the task ends — answered, failed, refused by the quota, or its stream read to the end, left or never read —; its
time to live, 300 seconds, frees the place of a process that died before. A task that finds every place taken throws
`TooManyTasks`, and nothing is counted nor called. The semaphore adds to the reservation and never replaces it.

The limits come from the meters, or from a limit provider of `phil/usage-meter` — for instance the features
`usage:ai_tokens:<limit>` of `phil/commerce-subscription-bundle` offers, so a paid offer raises the quota.
