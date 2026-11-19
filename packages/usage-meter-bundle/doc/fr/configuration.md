<!-- phil-dev source-sha256=8710cdd580bbca60cea754672183cd7af74d161215e2258f87e987a76c7aac4e -->
# Configuration

```yaml
phil_usage_meter:
    store: dbal                     # dbal, memory, or the id of a UsageStore service
    dbal:
        connection: default
        counter_table: phil_usage_counter
        key_table: phil_usage_key
    clock: clock
    limit_providers: [App\Usage\AdminOverrides, App\Usage\PlanLimits]
    subscription_features:
        enabled: false
        prefix: 'usage:'
    anchor_provider: App\Usage\SubscriptionAnchors
    meters:
        exports: { limit: 3, calendar: month, timezone: America/Toronto }
        requests: { limit: 600, rolling: 3600, buckets: 60 }
        ai.tokens: { limit: 50000, billing: '2026-01-01T00:00:00-05:00', months: 1 }
        projects: { limit: unlimited, calendar: year }
    rate_limiters:
        api: { meter: requests, key: ip, amount: 1, idempotency_key: false }
    idempotency_secret: '%kernel.secret%'
```

Chaque clé est facultative ; sans aucune, le bundle branche un `UsageMeter` sans compteurs sur le stockage DBAL.

## Le stockage

| Clé | Valeur par défaut | Sens |
|---|---|---|
| `store` | `dbal` | `dbal` : `Phil\UsageMeter\Dbal\DbalUsageStore` sur une connexion DBAL ; `memory` : `Phil\UsageMeter\InMemoryUsageStore`, les compteurs d'un seul processus ; toute autre valeur : l'identifiant d'un service qui implémente `Phil\UsageMeter\UsageStore` |
| `dbal.connection` | `default` | La connexion DoctrineBundle des tables, `doctrine.dbal.<name>_connection` |
| `dbal.counter_table` | `phil_usage_counter` | La table des compteurs : jusqu'à 63 lettres minuscules, chiffres ou `_` |
| `dbal.key_table` | `phil_usage_key` | La table des clés d'idempotence |

`Phil\UsageMeter\UsageStore` est un alias du stockage choisi, pour les services qui en ont besoin. Avec `dbal` et
`doctrine/orm`, `UsageSchemaListener` ajoute les deux tables au schéma que génère l'ORM pour cette connexion
([Installation](installation.md)). `store: dbal` sans `doctrine/dbal` fait échouer la construction du conteneur.

## Les compteurs

Chaque entrée de `meters` déclare un compteur, par son nom (jusqu'à 64 lettres minuscules, chiffres, `_`, `.` ou `-`,
qui commence par une lettre ou un chiffre), avec sa limite et exactement une période :

| Clé | Valeur par défaut | Sens |
|---|---|---|
| `limit` | obligatoire | La limite d'un sujet qu'aucun fournisseur de limites ne nomme : `unlimited`, ou un nombre entier à partir de zéro |
| `calendar` | aucune | `day`, `week` (ISO, à partir du lundi), `month` ou `year`, à partir de minuit, heure locale, dans `timezone` |
| `timezone` | `UTC` | Le fuseau horaire d'une période du calendrier |
| `rolling` | aucune | Une fenêtre glissante de tant de secondes |
| `buckets` | `1` | Les compartiments de la fenêtre glissante, de 1 à 1440, qui la divisent exactement |
| `billing` | aucune | Des périodes de facturation à partir de cette ancre, une date et une heure avec leur décalage ; une date YAML sans guillemets se lit comme minuit UTC |
| `months` | `1` | La durée d'une période de facturation, de 1 à 120 mois |

Le bundle construit chaque compteur pendant la construction du conteneur. Un compteur sans période ou avec deux, une
limite qui n'en est pas une, un fuseau horaire inconnu, des compartiments qui ne divisent pas la fenêtre, ou une ancre
qui n'est pas une date font échouer la construction avec un message qui nomme le compteur. La documentation de
`phil/usage-meter`, sous *Meters and periods*, dit comment chaque période découpe le temps.

## Les limites

| Clé | Valeur par défaut | Sens |
|---|---|---|
| `limit_providers` | `[]` | Des identifiants de service de `Phil\UsageMeter\LimitProvider`, interrogés dans l'ordre ; le premier qui nomme une limite l'emporte |
| `subscription_features.enabled` | `false` | Si les fonctionnalités des offres d'un abonné nomment des limites, avec `phil/commerce-subscription-bundle` |
| `subscription_features.prefix` | `usage:` | Ce qui commence une telle fonctionnalité : `usage:exports:20`, `usage:projects:unlimited` |

Les fournisseurs, puis les fonctionnalités d'abonnement, sont placés dans une `Phil\UsageMeter\LimitChain`, le service
`phil_usage_meter.limits` ; un sujet pour lequel aucun d'eux ne nomme de limite reçoit celle du compteur. Sans l'un ni
l'autre, `UsageMeter` lit seulement les limites des compteurs.

## Les ancres et l'horloge

| Clé | Valeur par défaut | Sens |
|---|---|---|
| `anchor_provider` | aucune | L'identifiant de service d'un `Phil\UsageMeter\AnchorProvider` : l'ancre propre à chaque sujet pour les compteurs comptés par cycle de facturation |
| `clock` | `clock` | L'identifiant de service de l'horloge PSR-20 dans laquelle les fenêtres sont lues |

## Les limiteurs de débit

| Clé | Valeur par défaut | Sens |
|---|---|---|
| `rate_limiters.<name>.meter` | obligatoire | Le compteur sur lequel compte le limiteur, déclaré dans `meters` |
| `rate_limiters.<name>.key` | `ip` | Pour qui compte une requête quand une règle ne nomme aucune clé : une stratégie de clé de `phil/rate-limit-bundle` (`ip`, `user`, `user_or_ip`, `ip_route`) ou l'identifiant d'un service qui implémente son `KeyResolver` |
| `rate_limiters.<name>.amount` | `1` | Ce que compte une requête quand une règle ne nomme aucune quantité, un ou plus |
| `rate_limiters.<name>.idempotency_key` | `false` | Si une requête POST, PUT, PATCH ou DELETE est comptée une fois par en-tête `Idempotency-Key` ; voir [rate-limiting.md](rate-limiting.md) |
| `idempotency_secret` | `%kernel.secret%` | La clé des empreintes sous lesquelles les clés d'idempotence sont gardées |

Un limiteur sur un compteur que la section `meters` ne déclare pas fait échouer la construction du conteneur, tout
comme des limiteurs sans que `PhilRateLimitBundle` soit enregistré. Les en-têtes, le secret des clés et la réponse
relèvent de la configuration de `phil/rate-limit-bundle`. [Limitation de débit](rate-limiting.md) dit comment ils
comptent.
