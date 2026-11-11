# HTTP clients

## A client behind its circuit

A circuit with `http_client` decorates that service with `CircuitBreakingHttpClient`, as
`phil_circuit_breaker.http_client.<key>`:

```yaml
framework:
    http_client:
        scoped_clients:
            payments.client:
                base_uri: 'https://api.payments.example'

phil_circuit_breaker:
    circuits:
        http.payments:
            failure_rate_percent: 50
            minimum_calls: 10
            window_seconds: 60
            open_seconds: 30
            http_client: payments.client
```

Every service that receives `payments.client` now receives it behind its circuit. One circuit per client, or per
destination: a client that reaches several partners is better split into scoped clients, each behind its own circuit,
so that one failing partner never trips the others.

## What is refused, what is counted

- While the circuit is open, or forced open by an override, a request is refused before it leaves with
  `CircuitOpen`, a `TransportExceptionInterface`: code that already handles an unreachable host handles it too.
- An answer of `5xx` or a transport error is a failure.
- Any other answer, `4xx` included, is a success: a `4xx` is the caller's mistake, not the third party's.

The outcome is recorded on the first chunk of the response, without waiting for its body, so the decorator keeps the
client asynchronous. Each request is measured up to that chunk, or up to its transport error: the duration is the
transport's own `total_time` when it gives a positive one, the monotonic time since the request was made otherwise.
It goes with the outcome, so that a circuit with `slow_call_milliseconds` counts the requests that last longer, and
opens when their share reaches `slow_call_rate_percent`.

```php
use Phil\CircuitBreakerBundle\Infrastructure\HttpClient\Exception\CircuitOpen;

try {
    $charge = $this->paymentsClient->request('POST', '/charges', ['json' => $payload])->toArray();
} catch (CircuitOpen) {
    return Charge::deferred($payload);        // the fallback
}
```

## Around any other call

A breaker can guard any other call: get it from `CircuitBreakers`, ask `isAvailable()`, then report with
`record()`, its duration included, or with `recordSuccess()` or `recordFailure()`. `phil/circuit-breaker` describes
it.
