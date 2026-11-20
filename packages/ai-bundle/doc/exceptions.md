# Exceptions

The bundle's exceptions implement `Phil\AiBundle\Domain\Exception\AiException`. None carries a prompt, an answer or a
key.

| Exception | Extends | Thrown when |
|---|---|---|
| `Domain\Exception\InvalidAiValue` | `InvalidArgumentException` | A task kind, a subject, a platform or model name, a token count, a price, a cost or a currency out of shape; a kind defined twice; a price in another currency than the list |
| `Domain\Exception\AlreadyDecided` | `LogicException` | A suggestion decided a second time, or the decision on a failed task |
| `Application\Exception\UnknownTask` | `LogicException` | A kind the configuration does not define |
| `Application\Exception\MissingPrompt` | `LogicException` | A kind without user prompt in the locale nor its fallbacks |
| `Application\Exception\NoUserKey` | `RuntimeException` | Under `require_user`, a task without owner, or whose owner has no key for the platform |
| `Application\Exception\TaskFailed` | `RuntimeException` | The model call failed; the provider's exception is the previous one |
| `Application\Exception\UnknownSuggestion` | `RuntimeException` | A decision on an identifier the journal does not hold |
| `Application\Exception\UnknownPlatform` | `LogicException` | A platform that is not declared, or a person's key for a platform without `user_key_factory`; reported as the previous exception of `TaskFailed` |

`run()` also lets through `Phil\UsageMeter\Exception\QuotaExceeded` when the subject's quota is reached: its `usage`
tells how much is used, the limit and when the window frees room.

Examples of messages:

```text
No task "draft.expand" is defined: add it under phil_ai.tasks.
The task "draft.rewrite" has no prompt "draft.rewrite.user" in the translation domain "phil_ai" for the locale "de".
The task runs only on the person's own key, and they have none for the platform "main".
The task "draft.rewrite" failed at the model (Symfony\AI\Platform\Exception\RateLimitExceededException).
The suggestion "0194f3d2-…" is already accepted.
```

A configuration out of shape fails when the container is built, with Symfony's `InvalidConfigurationException`.
