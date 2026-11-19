# Integrations

Each integration is used when its package is installed, or when the configuration asks for it; none is required.

## Rate limits with phil/rate-limit-bundle

The service `phil_api_keys.rate_limit_key` is a key strategy of `phil/rate-limit-bundle`: a request authenticated by a
token counts for `api_token:<token id>`, any other request is not counted by that rule. A limiter of that bundle names
it, or a limiter of `phil/usage-meter-bundle` that counts on a meter:

```yaml
phil_rate_limit:
    limiters:
        api: { policy: sliding_window, limit: 5000, interval: '1 hour', key: phil_api_keys.rate_limit_key }

# or, on a meter, so that a limit provider gives some tokens more
phil_usage_meter:
    meters:
        api_requests: { limit: 5000, rolling: 3600, buckets: 60 }
    rate_limiters:
        api: { meter: api_requests, key: phil_api_keys.rate_limit_key }
```

```php
use Phil\RateLimitBundle\Domain\Attribute\RateLimit;

#[RateLimit('api')]
#[RequiresScope('projects:read')]
final class ProjectController
```

The rule runs on `kernel.controller`, after the firewall, so the token is known; a refusal is answered `429` with
`Retry-After` and the rate-limit headers, which the counted responses carry too. On a meter, a limit provider's subject
is `api_token:` and the identifier. Pair the rule with one by client address for the requests without a token.

## Problem details with phil/api-contract-bundle

With `phil/api-contract-bundle` installed, `phil_api_keys.problem_responder`, named as the firewall's `entry_point`
and the authenticator's `failure_handler`, answers a `401` as RFC 9457 problem details, `application/problem+json`,
with the `WWW-Authenticate` challenge:

```json
{"type": "about:blank", "status": 401, "title": "Unauthorized", "detail": "The API token was refused."}
```

The `401` and `403` of `#[RequiresScope]` are an `UnauthorizedHttpException` and an `AccessDeniedHttpException` with
their challenges: under the `problem_details.paths` of that bundle, they are answered as problem details too.

## Anonymized addresses with phil/ip-anonymizer

The address a token was last used from is kept only when `last_used_address` says how:

```yaml
phil_api_keys:
    last_used_address: truncation   # 203.0.113.45 kept as 203.0.113.0, IPv6 to its /48
```

Another strategy — a keyed digest with a daily salt, a network, a region label — is a service implementing
`ClientAddressAnonymizer`; `AnonymizedClientAddress` adapts any strategy of `phil/ip-anonymizer`:

```yaml
services:
    app.daily_address:
        class: Phil\ApiKeysBundle\Infrastructure\IpAnonymizer\AnonymizedClientAddress
        arguments: ['@app.keyed_hash']   # a Phil\IpAnonymizer\KeyedHash

phil_api_keys:
    last_used_address: app.daily_address
```

The address is read as Symfony reads it behind its trusted proxies, and anonymized only when the use is written.

## The events, for an audit trail

`ApiTokens` dispatches three events through the PSR-14 dispatcher, the service `event_dispatcher` when there is one:

| Event | When |
|---|---|
| `TokenCreated` | A token is created, by a member, a command or a rotation |
| `TokenRevoked` | A token is revoked, the first time only |
| `TokenUsed` | A use is written, at most once per `last_used_throttle` |

Each carries `tokenId` only. A listener that writes to `phil/audit-bundle`, or to a log, reads the rest — the owner,
the scopes — from `ApiTokenRepository`, and decides what of it is personal data:

```php
use Phil\ApiKeysBundle\Domain\Event\TokenRevoked;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class AuditTokenRevocations
{
    public function __invoke(TokenRevoked $event): void
    {
        // $this->audit->record('api_token.revoked', ['token' => $event->tokenId]);
    }
}
```
