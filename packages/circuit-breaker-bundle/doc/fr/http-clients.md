<!-- phil-dev source-sha256=cd5f430385ab9c6746ef4328238e3478048c18f5ed243b605174b2eac9900c93 -->
# Clients HTTP

## Un client derrière son circuit

Un circuit doté de `http_client` décore ce service avec `CircuitBreakingHttpClient`, sous
`phil_circuit_breaker.http_client.<key>` :

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

Chaque service qui reçoit `payments.client` le reçoit désormais derrière son circuit. Un circuit par client, ou par
destination : un client qui atteint plusieurs partenaires gagne à être scindé en clients à portée limitée, chacun
derrière son propre circuit, pour qu'un partenaire en échec ne déclenche jamais les autres.

## Ce qui est refusé, ce qui est compté

- Tant que le circuit est ouvert, ou forcé ouvert par un forçage, une requête est refusée avant de partir avec
  `CircuitOpen`, une `TransportExceptionInterface` : le code qui gère déjà un hôte injoignable la gère aussi.
- Une réponse `5xx` ou une erreur de transport est un échec.
- Toute autre réponse, `4xx` compris, est un succès : un `4xx` est l'erreur de l'appelant, pas celle du tiers.

Le résultat est inscrit au premier fragment de la réponse, sans attendre son corps : le décorateur garde ainsi le client
asynchrone. Chaque requête est mesurée jusqu'à ce fragment, ou jusqu'à son erreur de transport : la durée est le
`total_time` du transport quand il en donne un positif, le temps monotone écoulé depuis la requête sinon. Elle
accompagne le résultat, pour qu'un circuit doté de `slow_call_milliseconds` compte les requêtes qui durent plus, et
s'ouvre quand leur part atteint `slow_call_rate_percent`.

```php
use Phil\CircuitBreakerBundle\Infrastructure\HttpClient\Exception\CircuitOpen;

try {
    $charge = $this->paymentsClient->request('POST', '/charges', ['json' => $payload])->toArray();
} catch (CircuitOpen) {
    return Charge::deferred($payload);        // the fallback
}
```

## Autour de tout autre appel

Un disjoncteur peut protéger tout autre appel : obtenez-le de `CircuitBreakers`, interrogez `isAvailable()`, puis
rapportez le résultat avec `record()`, sa durée comprise, ou avec `recordSuccess()` ou `recordFailure()`.
`phil/circuit-breaker` le décrit.
