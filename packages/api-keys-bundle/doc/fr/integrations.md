<!-- phil-dev source-sha256=3acb872539ff4e6c2c418692fc58f7af3c27de61a4a87e1cb5ba339aa1e4baca -->
# Intégrations

Chaque intégration est utilisée quand son paquet est installé, ou quand la configuration la demande; aucune n'est
obligatoire.

## Les limites de débit avec phil/rate-limit-bundle

Le service `phil_api_keys.rate_limit_key` est une stratégie de clé de `phil/rate-limit-bundle` : une requête
authentifiée par un jeton compte pour `api_token:<token id>`, et toute autre requête n'est pas comptée par cette règle.
Un limiteur de ce bundle la nomme, ou un limiteur de `phil/usage-meter-bundle` qui compte sur un compteur :

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

La règle s'exécute sur `kernel.controller`, après le pare-feu : le jeton est donc connu. Un refus reçoit la réponse
`429` avec `Retry-After` et les en-têtes de limite de débit, que portent aussi les réponses comptées. Sur un compteur,
le sujet d'un fournisseur de limites est `api_token:` suivi de l'identifiant. Associez la règle à une règle par adresse
du client pour les requêtes sans jeton.

## Les détails de problème avec phil/api-contract-bundle

Quand `phil/api-contract-bundle` est installé, `phil_api_keys.problem_responder`, nommé comme `entry_point` du pare-feu
et comme `failure_handler` de l'authentificateur, répond à un `401` par un détail de problème RFC 9457,
`application/problem+json`, avec le défi `WWW-Authenticate` :

```json
{"type": "about:blank", "status": 401, "title": "Unauthorized", "detail": "The API token was refused."}
```

Le `401` et le `403` de `#[RequiresScope]` sont une `UnauthorizedHttpException` et une `AccessDeniedHttpException` avec
leurs défis : sous les `problem_details.paths` de ce bundle, ils reçoivent aussi une réponse sous forme de détails de
problème.

## Les adresses anonymisées avec phil/ip-anonymizer

L'adresse depuis laquelle un jeton a été utilisé pour la dernière fois est gardée seulement quand `last_used_address`
indique comment :

```yaml
phil_api_keys:
    last_used_address: truncation   # 203.0.113.45 kept as 203.0.113.0, IPv6 to its /48
```

Une autre stratégie (une empreinte à clé avec un sel quotidien, un réseau, une étiquette de région) est un service qui
implémente `ClientAddressAnonymizer`. `AnonymizedClientAddress` adapte n'importe quelle stratégie de
`phil/ip-anonymizer` :

```yaml
services:
    app.daily_address:
        class: Phil\ApiKeysBundle\Infrastructure\IpAnonymizer\AnonymizedClientAddress
        arguments: ['@app.keyed_hash']   # a Phil\IpAnonymizer\KeyedHash

phil_api_keys:
    last_used_address: app.daily_address
```

L'adresse est lue comme Symfony la lit derrière ses mandataires de confiance, et anonymisée seulement quand
l'utilisation est écrite.

## Les événements, pour une piste d'audit

`ApiTokens` répartit trois événements par le répartiteur PSR-14, le service `event_dispatcher` quand il existe :

| Événement | Quand |
|---|---|
| `TokenCreated` | Un jeton est créé, par un membre, une commande ou un renouvellement |
| `TokenRevoked` | Un jeton est révoqué, la première fois seulement |
| `TokenUsed` | Une utilisation est écrite, au plus une fois par `last_used_throttle` |

Chacun porte seulement `tokenId`. Un écouteur qui écrit dans `phil/audit-bundle`, ou dans un journal, lit le reste (le
propriétaire, les portées) dans `ApiTokenRepository`, et décide ce qui en constitue des renseignements personnels :

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
