<!-- phil-dev source-sha256=d8792870def94d4f1c4dd2d3439b49deeef356421223e9b4c7a8062df4f00feb -->
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
| Une clé nouvelle dont la requête s'est terminée après l'expiration de sa réservation et la réservation de la clé par une autre requête | Problème `409` à la place de la réponse du contrôleur, qui n'est pas conservée : la clé répond pour l'autre requête. Un `5xx` du contrôleur est laissé tel quel |
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
s'exécute encore laisse une nouvelle tentative s'exécuter à côté d'elle. La requête en retard n'écrase alors jamais
l'issue de la nouvelle tentative — chaque réservation porte un jeton à elle, que `phil/idempotency` vérifie avant
d'écrire — et son client reçoit un problème `409` intitulé `IdempotencyListener::RESERVATION_LOST`, pour qu'une
nouvelle tentative lise l'issue de la requête qui tient la clé.

Seuls le statut, le corps et `idempotency.replayed_headers` sont conservés.

## Les contrôles d'accès avant le rejeu

L'écouteur s'exécute sur `kernel.controller_arguments`, à la priorité `IdempotencyListener::PRIORITY` (-10100), après
que Symfony a résolu les arguments du contrôleur et envoyé les écouteurs de ses attributs, à -10000 : `#[IsGranted]`,
`#[IsCsrfTokenValid]`, `#[IsSignatureValid]`, `#[RateLimit]`, et les résolveurs de valeur des arguments, comme une
entité chargée sous le filtre du locataire. Ils décident à chaque requête, un rejeu compris :

- un appelant qui a perdu une permission pendant que la clé était conservée est refusé par le contrôle d'accès, jamais
  servi avec le corps conservé ;
- une requête refusée par un contrôle d'accès ne lit aucune clé et n'en réserve aucune : une fois autorisée, la même
  clé exécute le contrôleur.

Un rejeu prend la place du contrôleur avec les attributs de l'original, pour que les écouteurs de réponse qui les
lisent — `#[Cache]`, l'obsolescence de `#[DeprecatedEndpoint]` — s'appliquent encore, et que le profileur nomme le vrai
contrôleur.

## La portée d'une clé

Une clé est rattachée à son appelant : deux requêtes ne partagent une clé que si elles viennent du même locataire, du
même client d'API et du même utilisateur. `CallerScopeResolver` construit la portée à partir de :

| Partie | Venue de | Sans elle |
|---|---|---|
| Le locataire | `IdempotencyTenantResolver` : par défaut `ActiveTenantResolver`, le locataire actif de `phil/tenancy-bundle`, que ce bundle résout et compare aux locataires que le compte peut atteindre | Aucune partie de locataire |
| Le client | `IdempotencyClientResolver` : par défaut `TokenAttributeClientResolver`, l'attribut `idempotency.client_attribute` (`client_id`) du jeton de sécurité | Aucune partie de client |
| L'utilisateur | L'identifiant de l'utilisateur du jeton de sécurité | Un appelant anonyme, ci-dessous |

La portée est `caller:` et le SHA-256 des parties présentes, chacune nommée et précédée de sa longueur : la table ne
contient aucun identifiant d'utilisateur, et un locataire nommé comme un client ne partage jamais ses clés.

Un appelant qui n'est ni un utilisateur ni un client ne se distingue pas d'un autre. `idempotency.anonymous` décide :

- `refuse`, la valeur par défaut : un problème `403` intitulé `CallerScopeResolver::ANONYMOUS` ; aucune clé n'est lue ni
  réservée ;
- `ip` : la portée est l'adresse du client, dans le locataire. Tout appelant anonyme derrière une même adresse — un
  mandataire d'entreprise, le NAT d'un opérateur, un Wi-Fi partagé — partage ses clés : l'un d'eux qui envoie la même
  clé, le même chemin et le même corps qu'un autre reçoit la réponse de l'autre. Ne le choisissez que pour les points de
  terminaison dont la réponse ne contient rien sur l'appelant, et réglez `trusted_proxies` de Symfony, sinon chaque
  appelant derrière le mandataire a son adresse.

### Plusieurs locataires

Une application qui sert plusieurs locataires depuis un même stockage doit mettre le locataire dans la portée. Avec
`phil/tenancy-bundle`, il n'y a rien à faire : son locataire actif est lu. Une application qui résout ses locataires
autrement donne à `IdempotencyTenantResolver` l'alias d'un service qui retourne le locataire qu'elle a vérifié — jamais
un hôte ni un en-tête que le client a choisi, que le client d'un autre locataire peut aussi envoyer :

```php
final readonly class TenantResolver implements IdempotencyTenantResolver
{
    public function __construct(private CurrentTenant $tenant) {}

    public function tenant(Request $request): ?string
    {
        return $this->tenant->get()?->reference();
    }
}
```

```yaml
services:
    Phil\ApiContractBundle\Infrastructure\Http\IdempotencyTenantResolver: '@App\Tenancy\TenantResolver'
```

Un locataire qui a son propre stockage — une base de données par locataire — est déjà à part ; le locataire dans la
portée ne nuit pas.

### Clients d'API

Un authentificateur de jetons d'accès qui connaît le client le pose sur le jeton, `$token->setAttribute('client_id', …)`,
ou sous le nom que donne `idempotency.client_attribute`. Une application dont les clients sont connus autrement donne
l'alias à `IdempotencyClientResolver` ; celle qui nomme ses appelants tout autrement donne l'alias à
`IdempotencyScopeResolver`.

## Modèle de menace

| Menace | Réponse |
|---|---|
| Un appelant rejoue la réponse d'un autre en devinant ou en réutilisant sa clé | La portée contient le locataire, le client et l'utilisateur : une clé n'est jamais partagée entre eux |
| Le client d'un locataire reçoit la réponse d'un autre locataire sur un stockage partagé | Le locataire vérifié est dans la portée ; l'empreinte couvre la méthode, le chemin, la requête et le corps, jamais l'hôte, le locataire doit donc venir de la portée |
| Un utilisateur qui a perdu une permission lit le corps conservé en rejouant la clé | Les contrôles d'accès du contrôleur et de ses arguments s'exécutent avant chaque rejeu |
| Une requête refusée bloque la clé pour la nouvelle tentative autorisée | Une requête refusée ne réserve aucune clé |
| Deux clés qui ne diffèrent que par la casse ou l'accent nomment une seule ligne sur MySQL ou MariaDB | L'écouteur de schéma construit la table pour la plateforme de la connexion : `scope` et `idempotency_key` en `utf8mb4_bin` |
| Des appelants anonymes derrière une même adresse partagent leurs clés | Refusés par défaut ; `anonymous: ip` est un choix explicite |
| La table révèle qui a appelé | La portée est un SHA-256 ; seule l'empreinte de la requête est conservée, avec la réponse |

Ce qui reste à l'application : la réponse conservée est rejouée telle qu'elle était, si bien qu'un point de terminaison
dont la réponse dépend d'autre chose que de l'appelant, de la clé et de la requête — une ressource qui a changé, un
prix — répond avec la première jusqu'à l'expiration de la clé ; et une règle de pare-feu ou d'`access_control`
s'exécute avant tout contrôleur, rejeu ou non.

## Le stockage

Avec DoctrineBundle, les clés sont dans la table `phil_idempotency` de `DbalIdempotencyStore`, sur
`idempotency.connection` : la portée, la clé, l'empreinte, l'état, la réponse conservée et son expiration, avec une
clé primaire sur la portée et la clé. Le bundle ajoute la table au schéma de cette connexion, construite pour sa
plateforme : sur MySQL et MariaDB, `scope` et `idempotency_key` prennent `utf8mb4_bin`, pour se comparer octet par octet.
Une réservation est une insertion atomique ; une clé expirée n'est jamais lue. `DbalIdempotencyStore::purge()` oublie
les clés expirées ; lancez-la depuis une tâche planifiée.

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

## Passer de la 0.2

- **Les appelants anonymes sont refusés.** Un point de terminaison `#[Idempotent]` appelé sans utilisateur ni client
  répond `403`. Authentifiez ses appelants, ou réglez `idempotency.anonymous: ip` en sachant que les appelants derrière
  une même adresse partagent leurs clés.
- **Les portées ont changé.** Une portée est maintenant `caller:` et un SHA-256, avec le locataire et le client : les
  clés enregistrées par la 0.2, `user:…` et `ip:…`, ne sont plus trouvées. Une nouvelle tentative qui arrive pendant le
  déploiement, durant la rétention, exécute de nouveau son contrôleur. Déployez quand les clients ne réessaient pas, ou
  gardez en tête la rétention des clés de la 0.2.
- **L'écouteur a bougé.** `IdempotencyListener::onController(ControllerEvent)` est maintenant
  `onControllerArguments(ControllerArgumentsEvent)`, sur `kernel.controller_arguments` à -10100. Une requête refusée par
  un contrôle d'accès ne réserve plus sa clé, et un rejeu exécute d'abord les contrôles d'accès.
- **La table sur MySQL et MariaDB.** Une table générée par la 0.2 à partir de l'écouteur de schéma compare `scope` et
  `idempotency_key` avec la collation par défaut de la connexion. `doctrine:migrations:diff` écrit maintenant la
  migration qui change les deux colonnes en `utf8mb4_bin` ; exécutez-la.
- **Un résolveur à vous** qui implémentait `IdempotencyScopeResolver` est conservé tel quel ; envisagez d'y mettre le
  locataire et le client dans sa portée.
