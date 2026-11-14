<!-- phil-dev source-sha256=a226ba28d3bdbb71d6ce7521f5373481cbd534ff353e97ac8ce611cfba510087 -->
# Configuration

```yaml
phil_rate_limit:
    secret: '%kernel.secret%'          # the key of the hashed keys
    headers: draft                     # draft, legacy, both or none
    error_template: '@PhilRateLimit/too_many_requests.html.twig'
    cache_pool: cache.app              # where the limiters keep their state
    lock_factory: null                 # a lock factory, for exact counts under concurrency
    single_server: false               # accepts a store local to the server in prod
    network:                           # needs phil/ip-anonymizer
        enabled: false
        ipv4_prefix: 32
        ipv6_prefix: 64
    limiters:
        api:
            policy: sliding_window     # fixed_window, sliding_window, token_bucket, compound or no_limit
            limit: 100
            interval: '1 minute'
            key: ip                    # ip, user, user_or_ip, ip_route, or a KeyResolver service id
            amount: 1
        reset: { policy: fixed_window, limit: 5, interval: '15 minutes', key: ip_route, lock_factory: lock.factory }
        monthly: { policy: fixed_window, limit: 10000, interval: '1 month', anchor_at: '2026-01-01', key: user }
        contact: { policy: token_bucket, limit: 3, rate: { interval: '10 minutes', amount: 1 } }
        strict: { policy: compound, limiters: [api, reset] }
        shared: { service: limiter.anonymous_api }
        sessions: { policy: sliding_window, limit: 30, interval: '1 minute', storage: app.rate_limit_storage }
        search: { policy: sliding_window, limit: 30, interval: '1 minute', observe: true }
    forms:
        contact: contact
        signup: [{ limiter: api, key: ip_route }, contact]
```

## Options

| Option | Valeur par défaut | Sens |
|---|---|---|
| `secret` | `%kernel.secret%` | La clé du HMAC-SHA-256 des clés `ip`, `user` et `ip_route`. |
| `headers` | `draft` | `draft` écrit `RateLimit-Policy` et `RateLimit` ; `legacy` écrit `RateLimit-Limit`, `RateLimit-Remaining` et `RateLimit-Reset` ; `both` ; `none` garde `Retry-After` seul. |
| `error_template` | `@PhilRateLimit/too_many_requests.html.twig` | La page d'un refus donné en HTML. |
| `cache_pool` | `cache.app` | Le pool de cache de chaque limiteur qui ne nomme ni pool ni stockage. |
| `lock_factory` | `null` | La fabrique de verrous de chaque limiteur qui n'en nomme aucune. |
| `single_server` | `false` | Accepte un pool ou un stockage propre au serveur dans l'environnement `prod`. |
| `network.enabled` | `false` | Les clés `ip` du réseau du client plutôt que de son hôte. |
| `network.ipv4_prefix`, `network.ipv6_prefix` | `32`, `64` | Les préfixes gardés. |
| `limiters` | `{}` | Les limiteurs, par nom : de 1 à 64 lettres, chiffres, `_`, `.` et `-`. |
| `forms` | `{}` | Les règles des formulaires par nom de formulaire. |

## Formulaires de connexion

Un formulaire de connexion est limité par le `login_throttling` de Symfony, pas par ce bundle : il compte chaque
tentative avant la vérification du mot de passe, si bien qu'un mauvais mot de passe et un bon pèsent autant, et il
regroupe les adresses IPv6 par /64, si bien qu'un hôte ne peut pas répartir ses tentatives sur son propre réseau.

```yaml
# config/packages/security.yaml
security:
    firewalls:
        main:
            login_throttling:
                max_attempts: 5
                interval: '15 minutes'
```

Le bundle limite tout le reste : les API, les autres formulaires (réinitialisation du mot de passe, contact,
inscription), et les contrôleurs et les routes déclarés avec `#[RateLimit]` ou `_rate_limit`.

## Un limiteur

| Option | Sens |
|---|---|
| `policy` | `fixed_window`, `sliding_window`, `token_bucket`, `compound` ou `no_limit` ; exactement un de `policy` et `service`. |
| `service` | L'identifiant d'un service `RateLimiterFactoryInterface`, comme un limiteur de la configuration `rate_limiter`. |
| `limit` | Les coups qu'une fenêtre permet, ou la taille d'un seau ; requis sauf pour `no_limit`, `compound` et `service`. |
| `interval` | La durée d'une fenêtre, telle que la lit `symfony/rate-limiter` : `'1 minute'`, `'15 minutes'`, `'1 month'`. |
| `rate.interval`, `rate.amount` | Le remplissage d'un seau à jetons : `amount` jetons à chaque `interval`. |
| `anchor_at` | Une fenêtre fixe alignée sur le calendrier à partir de ce moment. |
| `limiters` | Les limiteurs que compte un composé, chacun une politique ou un service déclaré à côté, jamais un composé. |
| `key` | La stratégie de clé d'une règle qui n'en nomme aucune, `ip` par défaut. |
| `amount` | Ce que consomme une règle qui n'en nomme aucun, `1` par défaut, jamais plus que `limit`. |
| `cache_pool`, `storage`, `lock_factory` | Le pool, le service de stockage ou la fabrique de verrous propres à ce limiteur. |
| `observe` | `true` compte et signale sans refuser : voir [Observer une limite](#observer-une-limite). `false` par défaut. |

Chaque limiteur est construit une fois à la construction du conteneur : une limite absente, un intervalle que
`symfony/rate-limiter` ne sait pas lire, une ancre sur une autre politique, un composé de rien ou d'un limiteur inconnu
fait échouer la construction avec le chemin de l'option.

## Observer une limite

Un limiteur en observation compte chaque requête comme les autres, dans le même stockage, mais n'en refuse aucune.
Quand il aurait refusé, la requête continue, et le bundle :

- répartit `Application\Event\RateLimitObserved`, avec le limiteur, la clé hachée, la décision, la route et le
  formulaire, comme les porte `RateLimitExceeded` ;
- journalise un avertissement, `The rate limit "{limiter}" in observation would have refused a request.`, sur le canal
  `rate_limit` de Monolog, avec le limiteur, la route, le formulaire, la limite et les secondes d'attente, jamais la clé.

Sa décision est laissée hors des en-têtes de la réponse, puisque la requête n'a pas été limitée. On observe d'abord une
limite pour la mesurer sur du vrai trafic, puis on l'applique en retirant `observe` :

```yaml
phil_rate_limit:
    limiters:
        search: { policy: sliding_window, limit: 30, interval: '1 minute', observe: true }
```

Un limiteur d'un autre paquet ou de l'application observe avec `observe: true` dans son étiquette. À côté d'un limiteur
appliqué, un limiteur observé n'arrête jamais la requête ; celui qui est appliqué la refuse toujours.

## Un stockage partagé par les serveurs

Dans l'environnement `prod`, la construction échoue sur un limiteur qui compte dans un stockage que chaque serveur garde
pour lui-même : son pool se termine sur l'adaptateur de système de fichiers, APCu, tableau ou système de
`symfony/cache`, ou son stockage est `InMemoryStorage` ou un `CacheStorage` qui reçoit un tel pool. Un client pourrait
sinon dépenser toute la limite sur chaque serveur. La vérification est la garde de `phil/rate-limit`, que le bundle
requiert. `cache.app` est sur le système de fichiers par défaut : donnez-lui un adaptateur partagé, ou nommez un pool
qui en a un.

```yaml
phil_rate_limit:
    single_server: true
```

`single_server: true` dit que l'application s'exécute sur un seul serveur, et désactive la vérification. Un limiteur de
la politique `no_limit` ne compte rien et n'est pas vérifié. Une fabrique nommée par `service` appartient à
l'application.
