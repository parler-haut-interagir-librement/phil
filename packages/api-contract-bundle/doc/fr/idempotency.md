<!-- phil-dev source-sha256=dd9f5d7eb909c747cde27f3062a681a178673e172d3c0144172bf3d39f15de9b -->
# Idempotence

Un contrôleur marqué `#[Idempotent]` exige un en-tête `Idempotency-Key` et s'exécute une fois par clé : une nouvelle
tentative reçoit de nouveau la première réponse. L'écouteur exécute la garde de `phil/idempotency` autour du
contrôleur ; ses clés, ses empreintes, ses états et ses stockages y sont documentés.

```php
use Phil\ApiContractBundle\Infrastructure\Http\Idempotent;

#[Route('/api/orders', methods: ['POST'])]
#[Idempotent]                 // or #[Idempotent(ttl: 3600)], at least 1
public function create(Request $request): Response
```

| La requête | La réponse |
|---|---|
| Aucune clé, ou une clé illisible | Problème `400` ; pour une clé illisible, un `detail` fixe qui dit ce qu'est une clé, jamais le message de l'exception |
| Une clé nouvelle | Le contrôleur s'exécute ; sa réponse est conservée, sauf si c'est un `5xx` ou un flux — la clé est alors marquée échouée, pour que le client puisse réessayer |
| Une clé déjà répondue, avec la même méthode, le même chemin, la même requête et le même corps | La réponse conservée, avec `Idempotent-Replayed: true` ; le contrôleur ne s'exécute pas |
| Une clé déjà utilisée avec une autre requête | Problème `422`, que la première requête soit terminée, en cours ou échouée |
| Une clé dont la première requête s'exécute encore | Problème `409` avec `Retry-After` : les secondes qui restent à sa réservation |
| Une clé dont la première requête a échoué, avec la même requête | Le contrôleur s'exécute de nouveau |

Chaque refus est un problème RFC 9457, `application/problem+json`. Les deux refus suivent le brouillon IETF de
l'en-tête : `422` pour une clé réutilisée avec une autre requête, une erreur que le client doit corriger, puisque la
réessayer n'aboutit jamais ; `409` pour une clé dont la première requête s'exécute encore, que le client réessaie après
`Retry-After`.

Une clé est réservée pendant `idempotency.reservation_ttl` secondes (300 par défaut, ou la rétention propre de la clé
si elle est plus courte) pendant que sa première requête s'exécute, puis conservée pendant sa rétention,
`idempotency.ttl` ou le `ttl` de l'attribut, une fois répondue ou échouée. Un processus qui meurt avant sa réponse —
une erreur fatale, un délai dépassé, un processus de travail tué — ne laisse rien pour compléter la clé : ses
nouvelles tentatives reçoivent donc `409` jusqu'à l'expiration de la réservation, et non pendant toute la rétention.
Gardez la réservation plus longue que la requête la plus lente : une réservation qui expire pendant que sa requête
s'exécute encore laisse une nouvelle tentative s'exécuter à côté d'elle.

Seuls le statut, le corps et `idempotency.replayed_headers` sont conservés. Un rejeu remplace le contrôleur ; l'écouteur
s'exécute donc après les écouteurs de `kernel.controller` qui lisent le contrôleur de la route — `DeprecationListener`,
le collecteur de requêtes du profileur — : une réponse rejouée d'un point de terminaison obsolète annonce encore son
obsolescence. Une clé est rattachée à son appelant : `CallerScopeResolver` prend l'identifiant de l'utilisateur
authentifié, `user:…`, sinon l'adresse du client, `ip:…`. Une API dont les appelants sont des clients dotés de leurs
propres identifiants donne à `IdempotencyScopeResolver` l'alias de son propre résolveur.

## Le stockage

Avec DoctrineBundle, les clés sont dans la table `phil_idempotency` de `DbalIdempotencyStore`, sur
`idempotency.connection` : la portée, la clé, l'empreinte, l'état, la réponse conservée et son expiration, avec une
clé primaire sur la portée et la clé. Le bundle ajoute la table au schéma de cette connexion. Une réservation est une
insertion atomique ; une clé expirée n'est jamais lue. `DbalIdempotencyStore::purge()` oublie les clés expirées ;
lancez-la depuis une tâche planifiée.

Avec `idempotency.cache_pool`, les clés sont plutôt dans ce pool, par `CacheIdempotencyStore`, chaque réservation sous
un verrou de `idempotency.lock_factory`. Le pool et le stockage des verrous doivent être partagés par chaque serveur qui
répond à la même API.

```yaml
# config/packages/phil_api_contract.yaml
phil_api_contract:
    idempotency:
        cache_pool: cache.idempotency
```

Sans stockage — ni DoctrineBundle, ni pool de cache, ni alias de `IdempotencyStore` —, un contrôleur `#[Idempotent]`
échoue avec une `LogicException` qui le dit.
