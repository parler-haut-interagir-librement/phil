# The guard

`IdempotencyGuard` runs the sequence of the store: find, decide, reserve, then complete or fail. It keeps no state
between calls, so one instance serves every request of a long-running worker.

```php
new IdempotencyGuard(IdempotencyStore $store, ClockInterface $clock, int $retention = 86_400, int $reservationTtl = 300)
```

- `$retention`: how many seconds an answered or failed key is kept. It must **outlast any plausible outage**, or
  the retry the key exists to absorb arrives after the key is forgotten and runs twice.
- `$reservationTtl`: how many seconds a key stays in progress before its answer, or the retention if shorter. Make
  it longer than the slowest run: a reservation that expires while its run still goes lets a retry run beside it.

Both are at least 1 second. Each method takes an optional `$retention` that replaces the guard's for one key.

## `run()`, around an operation

```php
/** @param Closure(): StoredResponse $operation */
public function run(IdempotencyScope $scope, IdempotencyKey $key, PayloadFingerprint $fingerprint, Closure $operation, ?int $retention = null): IdempotentRun
```

| Outcome | What `run()` does |
|---|---|
| `Proceed` | Runs `$operation`, keeps its response, returns it with `replayed: false`. A throwable marks the key failed and is thrown again. |
| `Replay` | Returns the stored response with `replayed: true`, without running anything. |
| `Reused` | Throws `IdempotencyKeyReused`. |
| `InProgress` | Throws `RequestInProgress`, whose `retryAfter` is the seconds left on the reservation. |

## `begin()`, `complete()`, `fail()`, for a start and an end seen apart

A listener that sees the request and the response in two events splits the run:

```php
$decision = $guard->begin($scope, $key, $fingerprint);

match ($decision->outcome) {
    IdempotencyOutcome::Replay => $response = $decision->replay(),
    IdempotencyOutcome::Reused => throw IdempotencyKeyReused::withAnotherRequest(),
    IdempotencyOutcome::InProgress => throw $guard->inProgress($decision),
    IdempotencyOutcome::Proceed => null, // reserved: run, then complete() or fail()
};
```

`begin()` reserves on `Proceed`. When another run takes the key between the read and the reservation, it reads
again and decides on what took it — a replay, a reuse, a run in progress. After three lost reservations it answers
`InProgress`. `inProgress($decision)` builds the exception with the seconds left on the record.

## An API

```php
try {
    $run = $guard->run(
        IdempotencyScope::of('client:'.$clientId),
        IdempotencyKey::fromHeader($request->headers->get(IdempotencyKey::HEADER)),
        PayloadFingerprint::ofRequest($request->getMethod(), $request->getRequestUri(), $request->getContent()),
        static fn (): StoredResponse => StoredResponse::of(201, ['Location' => ['/orders/7']], $body),
    );
} catch (MissingIdempotencyKey|InvalidIdempotencyValue) {
    // 400 problem
} catch (IdempotencyKeyReused) {
    // 422 problem
} catch (RequestInProgress $inProgress) {
    // 409 problem, Retry-After: $inProgress->retryAfter
}
```

A `5xx` the client may retry is a failure: throw from the operation, or call `fail()` after `begin()`. A `4xx` is an
answer like any other, and is replayed.

## A payment webhook

A payment provider sends each event at least once. Its event identifier is the key, its sender the scope; the
fingerprint covers the event type and the object it is about, not the delivery timestamp:

```php
$guard->run(
    IdempotencyScope::of('webhook:payments'),
    IdempotencyKey::fromString($event->id),
    PayloadFingerprint::ofParts($event->type, $event->objectId),
    static function () use ($event): StoredResponse {
        $payments->apply($event);

        return StoredResponse::nothing();
    },
);
```

Verify the signature of the webhook before the guard: an unsigned event must not take a key.

## A form sent twice

Render a key in a hidden field, `IdempotencyKey::generate()->value`, with the form. On submission, the session is the
scope and the submitted fields the fingerprint; a second click replays the redirection of the first:

```php
$run = $guard->run(
    IdempotencyScope::of('session:'.$session->getId()),
    IdempotencyKey::fromString($form->get('_idempotency_key')->getData()),
    PayloadFingerprint::ofParts('contact', $data->email, $data->message),
    static fn (): StoredResponse => StoredResponse::of(303, ['Location' => ['/thanks']]),
    retention: 3_600,
);
```

The CSRF token of the form is not a key: it is shared by every form of a session.

## Messenger

See [Messenger](messenger.md): a middleware runs the guard around the handlers of a received message.

Next: [stores](stores.md).
