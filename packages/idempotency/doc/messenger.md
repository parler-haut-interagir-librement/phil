# Messenger

`Messenger\IdempotencyMiddleware` handles a received message once per key.

```php
new IdempotencyMiddleware(IdempotencyGuard $guard, bool $compareContent = true)
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

- An `IdempotencyStamp(string $key, string $scope = 'messenger', ?PayloadFingerprint $fingerprint = null)` names the
  intention the message carries out: two messages dispatched for the same intention — a retried controller, a webhook
  received twice — are handled once. The stamp checks its key and its scope when it is created, at the dispatch. Both
  are identifiers, never data. A key that comes from a client — the `Idempotency-Key` of a request — is unique only to
  that client: give the stamp a scope that names it, the member, the tenant or the API client, or two clients who pick
  the same key for the same message class would see the second message acknowledged without being handled.
- Without a stamp, the `MessageIdStamp` of Messenger is the key: Messenger keeps it across retries, the failure
  transport and replays, so a redelivered message is handled once.
- An envelope with neither passes through.

## The fingerprint

A key reused with another message is refused, as an HTTP request that reuses a key with another body. The fingerprint
is the message class and its content, read from the first of:

1. the `$fingerprint` of the `IdempotencyStamp`, given by the producer that knows which fields make the intention;
2. `idempotencyFingerprint()`, when the message implements `Contract\FingerprintedMessage`;
3. every property of the message, private ones included, read down to its scalars with the class of each object:
   two messages of one class whose properties hold the same values are one intention.

```php
final readonly class RefundPayment implements FingerprintedMessage
{
    public function __construct(
        public string $paymentId,
        public int $amount,
        public \DateTimeImmutable $requestedAt,
    ) {
    }

    public function idempotencyFingerprint(): PayloadFingerprint
    {
        // The moment of the request differs between two dispatches of one refund: it is left out.
        return PayloadFingerprint::ofParts($this->paymentId, (string) $this->amount);
    }
}
```

A message whose content cannot be read — a closure, a resource, bytes that are not UTF-8, or more than
`IdempotencyMiddleware::MAX_DEPTH` (32) levels of objects and arrays — and that gives no fingerprint is refused with
`UnrecoverableMessageHandlingException`, whose message names the interface to implement.

`$compareContent` set to `false` compares the message class only: a key used by two classes is a reuse, and a second
message of the same class under the same key is acknowledged whatever its content. Use it only when the key alone
names the intention, such as the `MessageIdStamp` of Messenger, which a redelivery keeps with the same message.

The stamp keeps the hexadecimal SHA-256, never the fields, and the store keeps the fingerprint only.

## What it guards

Only a received envelope, one with a `ReceivedStamp`: a duplicate arrives through a transport, and the dispatch of
an asynchronous message is not its handling.

| The key | The middleware |
|---|---|
| New, or failed | Runs the next middleware and the handlers, and keeps the key as completed. |
| Completed | Returns the envelope without running the handlers: the message is acknowledged. |
| In progress elsewhere | Throws `RecoverableMessageHandlingException`, with a retry delay of the seconds left on the reservation. |
| Used by another message class or another content | Throws `UnrecoverableMessageHandlingException`: no retry. |

A handler that throws marks the key failed, so that the retry Messenger schedules runs it again.

A handler that outlives its reservation, while a redelivery of the same message reserved the key, ends with
`ReservationLost`: its outcome is not kept, and the retry Messenger schedules finds the other delivery's record —
acknowledged once it has completed. Set the reservation of the guard longer than the slowest handler.

## Upgrading from 0.2

The fingerprint of a message now covers its content. A key recorded by 0.2 holds the fingerprint of the class only:
a redelivery of such a message, within the retention, is refused as a reuse instead of being acknowledged. Let the
queues drain before the upgrade, or construct the middleware with `compareContent: false` until the retention of the
keys recorded before it has passed.

Next: [exceptions](exceptions.md).
