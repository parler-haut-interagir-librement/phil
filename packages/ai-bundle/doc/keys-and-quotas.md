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

- **The meter of tokens** is checked before a task: the subject must have room for the task's `reserve`, its usual
  size, or `QuotaExceeded` is thrown and nothing is called. After the call, the tokens spent are recorded, the task's
  identifier as idempotency key; a task larger than its reserve may take the subject past its limit, and the next
  task is refused until the period frees room.
- **The meter of tasks** consumes one task before each starts, atomically, the task's identifier as idempotency key.
  When the call then fails, or a stream is left before its end, `phil/usage-meter`'s `refund()` gives that task back:
  a failure costs the subject nothing.
- **The budget** is a meter of the subject's spending, in micros of the price list's currency: the subject must have
  room left before a task, and the task's cost is recorded after it. A paid offer raises the limit; a free tier may
  have none at all and rely on the meter of tokens.
- **The ceiling** is the same meter of spending for the whole application, counted on one subject of its own,
  `ceiling_subject`, `phil_ai.application` by default: once the month's spending reaches it, every task is refused
  until the period ends or the limit is raised. Every task records on that one counter, so it is a point of contention
  under heavy load; a budget per subject spreads the writes.
- **The person's own key** is not counted unless `count_user_keys` is true: they pay their provider.

```yaml
phil_ai:
    usage_meter: 'Phil\UsageMeter\UsageMeter'
    quota:
        tokens_meter: ai_tokens
        tasks_meter: ai_tasks
        budget_meter: ai_budget      # a meter of micros per subject: 5000000 is 5.00 a month
        ceiling_meter: ai_ceiling    # a meter of micros for the application
```

A budget or a ceiling checks that room is left, not that the next task fits: a task's cost is known after it, so the
last task of a period may take the spending past the limit, by one task at most per request running at that moment.

The limits come from the meters, or from a limit provider of `phil/usage-meter` — for instance the features
`usage:ai_tokens:<limit>` of `phil/commerce-subscription-bundle` offers, so a paid offer raises the quota.
