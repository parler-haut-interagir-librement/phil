<!-- phil-dev source-sha256=c77368d73405a811515a744578eea7bfd9d8d34747f80c6ae89d8944f4051f52 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les changer, et son CHANGELOG dit comment.

## Les ports écrits par l'application ou par un autre paquet

### `Limiter`

`Phil\RateLimitBundle\Application\Contract\Limiter` : un limiteur que nomment les règles. Enregistrez-le avec
l'étiquette `phil_rate_limit.limiter` et `name`, et au besoin `key`, `amount` et `observe`, un booléen qui signale ses
refus sans les appliquer.

| Méthode | Contrat |
|---|---|
| `consume(string $key, int $amount, Request $request): Decision` | Consomme et décide ; garde son état dans son stockage, jamais dans le service |

```yaml
services:
    app.quota_limiter:
        class: App\RateLimit\QuotaLimiter
        tags: [{ name: phil_rate_limit.limiter, key: user, amount: 1 }]
```

Le nom compte de 1 à 64 lettres, chiffres, `_`, `.` et `-` ; deux limiteurs d'un même nom, une étiquette sans nom, une
quantité inférieure à un ou une classe qui n'implémente pas l'interface font échouer la construction.

### `KeyResolver`

`Phil\RateLimitBundle\Application\Contract\KeyResolver` : pour qui compte une requête. Les services configurés
automatiquement sont des stratégies sous leur identifiant ; une étiquette `phil_rate_limit.key_resolver` avec
`strategy` donne un nom plus court.

| Méthode | Contrat |
|---|---|
| `keyOf(Request $request): ?string` | Une clé, de 1 à 200 octets, hachée quand elle identifie une personne ; `null` laisse la requête non comptée |

### `AddressNormalizer`

`Phil\RateLimitBundle\Application\Contract\AddressNormalizer` : l'adresse que hache la clé `ip`. Remplacez le service
`phil_rate_limit.network_address`, ou décorez `phil_rate_limit.key.ip`.

| Méthode | Contrat |
|---|---|
| `normalize(string $address): string` | Le texte haché à la place de l'adresse |

## Services

| Service | Rôle |
|---|---|
| `Phil\RateLimitBundle\Application\RateLimitEnforcer` (public) | Soumet une requête à des règles, depuis du code qui limite à sa façon |
| `phil_rate_limit.key.ip`, `.user`, `.user_or_ip`, `.ip_route` | Les stratégies de clé |
| `phil_rate_limit.limiter.<name>`, `phil_rate_limit.factory.<name>` | Chaque limiteur configuré et sa fabrique |
| `phil_rate_limit.attribute.<name>` | Chaque limiteur, étiqueté ou d'un autre paquet, comme le nomme le `#[RateLimit]` de Symfony : une fabrique étiquetée `rate_limiter` |
| `Phil\RateLimitBundle\Infrastructure\RateLimiter\AttributeLimits` | Ce par quoi ces fabriques consomment |

```php
$decision = $enforcer->enforce($request, [new Rule('exports', 5)]);
```

## Événements

| Événement | Quand |
|---|---|
| `Phil\RateLimitBundle\Application\Event\RateLimitExceeded` | Un limiteur a refusé une requête ou une soumission, avant la réponse |
| `Phil\RateLimitBundle\Application\Event\RateLimitObserved` | Un limiteur en observation aurait refusé une requête ou une soumission, qui continue |
| `Symfony\Component\RateLimiter\Event\RateLimitExceededEvent` | L'événement de Symfony pour un refus : réparti par le bundle après `RateLimitExceeded` sur ses limiteurs, avec la clé hachée, et par l'écouteur de Symfony sur les limiteurs de la configuration du framework, avec la clé telle que Symfony l'a calculée |

## Gabarits et traductions

| Élément | Redéfinition |
|---|---|
| `@PhilRateLimit/too_many_requests.html.twig` | `templates/bundles/PhilRateLimitBundle/`, ou `error_template` |
| Le domaine `phil_rate_limit` | `translations/phil_rate_limit.<locale>.yaml` |
