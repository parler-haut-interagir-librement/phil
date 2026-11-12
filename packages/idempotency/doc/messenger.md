# Messenger

`Messenger\IdempotencyMiddleware` handles a received message once per key.

```php
new IdempotencyMiddleware(IdempotencyGuard $guard)
```

Register the guard and the middleware as services, then add the middleware to a bus, before the handlers:

```yaml
framework:
    messenger:
        buses:
            messenger.bus.default:
                middleware:
                    - Phil\Idempotency\Messenger\IdempotencyMiddleware
```

## The key

- An `IdempotencyStamp(string $key, string $scope = 'messenger')` names the intention the message carries out: two
  messages dispatched for the same intention — a retried controller, a webhook received twice — are handled once.
  The stamp checks its key and its scope when it is created, at the dispatch. Both are identifiers, never data.
  A key that comes from a client — the `Idempotency-Key` of a request — is unique only to that client: give the
  stamp a scope that names it, the member, the tenant or the API client, or two clients who pick the same key for the
  same message class would see the second message acknowledged without being handled.
- Without a stamp, the `MessageIdStamp` of Messenger is the key: Messenger keeps it across retries, the failure
  transport and replays, so a redelivered message is handled once.
- An envelope with neither passes through.

The fingerprint is the message class: a key used by two classes is a reuse.

## What it guards

Only a received envelope, one with a `ReceivedStamp`: a duplicate arrives through a transport, and the dispatch of
an asynchronous message is not its handling.

| The key | The middleware |
|---|---|
| New, or failed | Runs the next middleware and the handlers, and keeps the key as completed. |
| Completed | Returns the envelope without running the handlers: the message is acknowledged. |
| In progress elsewhere | Throws `RecoverableMessageHandlingException`, with a retry delay of the seconds left on the reservation. |
| Used by another message class | Throws `UnrecoverableMessageHandlingException`: no retry. |

A handler that throws marks the key failed, so that the retry Messenger schedules runs it again.

Next: [exceptions](exceptions.md).
