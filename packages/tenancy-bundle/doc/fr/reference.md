<!-- phil-dev source-sha256=f2a8569b11e5c34f723321721394fda4e1ee37955fb3cd48f701b7cf0ea463cc -->
# Référence

Chaque type public du bundle, par couche.

## `Phil\TenancyBundle\Domain`

| Type | Sorte | Rôle | État |
|---|---|---|---|
| `ValueObject\TenantId` | `final readonly class`, `Stringable` | Référence de locataire opaque, propre à une application ; égalité octet par octet ; au plus 255 octets | Implémenté et testé |
| `ValueObject\ReachableTenants` | `final readonly class`, `Countable`, `IteratorAggregate` | Les locataires dans lesquels un compte peut agir ; doublons refusés ; peut être vide | Implémenté et testé |
| `Resolution\ActiveTenantRule` | `final readonly class`, fonctions statiques | `resolve()` et `switchTo()` contre l'ensemble atteignable | Implémenté et testé |
| `Resolution\Resolution` | `final readonly class` | Issue, ensemble atteignable, `staleChoiceDropped`, `tenant()`, `tenantOrNull()` | Implémenté et testé |
| `Resolution\Outcome` | énumération adossée à des chaînes | `Active`, `SelectionRequired`, `NoneReachable` | Implémenté et testé |
| `Attribute\TenantScoped` | attribut de classe | Marque une entité appartenant à un locataire et nomme son champ de locataire (`tenant` par défaut) | Implémenté et testé |
| `Exception\TenantNotReachable` | `DomainException`, `DomainError` | Un changement vers un locataire hors de l'ensemble atteignable | Implémenté et testé |
| `Exception\NoActiveTenant` | `DomainException`, `DomainError` | Le locataire a été demandé alors qu'aucun n'est actif | Implémenté et testé |
| `Shop\ShopNetwork` | `final readonly class` | Les boutiques, les catalogues que chacune inclut, sur un seul niveau ; les comptes clients partagés | Implémenté et testé |
| `Shop\OrderOrigin` | `final readonly class` | La boutique où une commande a été passée et les catalogues de ses lignes | Implémenté et testé |
| `Placement\Placement` | `final readonly class` | Où les données d'un locataire vivent : `shared()`, ou `dedicated()` dans une base nommée ; le nom est vérifié | Implémenté et testé |

## `Phil\TenancyBundle\Application`

| Type | Sorte | Rôle | État |
|---|---|---|---|
| `ActiveTenant` | `final readonly class` | `resolve()`, `switchTo()`, `leave()` sur les deux ports | Implémenté et testé ; enregistré dès que `reachable_tenants` est réglé |
| `TenantContext` | `final class` | Le locataire de la requête, du message ou de la commande courante ; `enter()`, `leave()`, `within()`, `require()` | Implémenté et testé ; enregistré |
| `Contract\ActiveTenantStore` | port | L'endroit où le locataire choisi est mémorisé | `SessionActiveTenantStore` dans le bundle, ou celui de l'application |
| `Contract\ReachableTenantsProvider` | port | L'origine de l'ensemble atteignable ; échoue en mode fermé | L'application l'implémente |
| `Contract\TenantActivation` | port | Ce qui suit le locataire actif | `DoctrineTenantActivation` dans le bundle, et celles de l'application |
| `Contract\HostTenants` | port | Le locataire qu'un hôte sert | `ConfiguredHostTenants` dans le bundle, ou celui de l'application |
| `Contract\HostAliases` | port | L'hôte canonique qu'un alias représente | `ConfiguredHostTenants` dans le bundle, ou celui de l'application |
| `Contract\AccountSharing` | port | Si un compte connecté sur un locataire l'est sur un autre | `ConfiguredAccountSharing` dans le bundle, ou celui de l'application |
| `Contract\TenantPlacement` | port | Si un locataire vit dans la base partagée ou dans une base à lui | Un bundle de base de données, ou l'application ; le bundle n'enregistre aucune implémentation |
| `Shop\ShopContext` | `final readonly class` | La boutique de la requête : ses catalogues, ce qu'elle vend, l'origine d'une commande | Implémenté et testé ; enregistré |
| `Event\HostTenantResolved` | `final readonly class` | L'hôte et le locataire entré pour lui | Implémenté et testé |

## `Phil\TenancyBundle\Infrastructure`

| Type | Sorte | Rôle | État |
|---|---|---|---|
| `Doctrine\ActiveTenantFilter` | `final class`, `SQLFilter` de l'ORM | Restreint les entités `#[TenantScoped]` au locataire actif ; échoue en mode fermé | Implémenté et testé ; enregistré activé par le bundle |
| `Exception\MisconfiguredTenantScope` | `final class`, `LogicException` | Une portée que le filtre ne peut pas appliquer | Implémenté et testé |
| `Doctrine\DoctrineTenantActivation` | `final readonly class`, `TenantActivation` | Règle le locataire du contexte sur chaque filtre activé ; détache de la table d'identité les entités liées à un locataire quand le locataire change ; un filtre neuf à la sortie | Implémenté et testé |
| `Doctrine\TenantColumn` | `final class` | `of()` : la colonne du locataire d'une entité, comme la lisent le filtre et les politiques ; `field()` : le champ ou l'association qui la contient | Implémenté et testé |
| `Doctrine\EntityTenant` | `final class` | `of()` : la référence du locataire que détient une entité, par un champ ou par l'identifiant d'une entité locataire associée | Implémenté et testé |
| `Doctrine\TenantWriteGuard` | `final class`, écouteur `onFlush` | Refuse un vidage qui écrit une entité liée à un locataire autre que le locataire actif | Implémenté et testé ; enregistré avec `doctrine_filter` |
| `Exception\CrossTenantWrite` | `final class`, `RuntimeException` | Le vidage que la garde d'écriture a refusé | Implémenté et testé |
| `Doctrine\RowLevelSecurity\RowLevelSecurityConfig` | `final readonly class` | La variable de session et le nom de la politique, vérifiés | Implémenté et testé |
| `Doctrine\RowLevelSecurity\TenantSession` | `final class`, `TenantActivation` | Pose le locataire actif sur chaque connexion PostgreSQL ouverte ; le vide à la sortie | Implémenté et testé sur PostgreSQL |
| `Doctrine\RowLevelSecurity\TenantSessionMiddleware`, `TenantSessionDriver` | intergiciel et pilote DBAL | Remettent chaque nouvelle connexion à `TenantSession` | Implémenté et testé sur PostgreSQL |
| `Doctrine\RowLevelSecurity\RowLevelSecurityPolicies`, `TenantTable` | `final readonly class` | Les tables par locataire et les instructions qui activent ou retirent leur politique | Implémenté et testé sur PostgreSQL |
| `Console\RowLevelSecurityPoliciesCommand` | commande console | `phil:tenancy:rls:policies` : les instructions, pour une migration | Implémenté et testé |
| `Console\RowLevelSecurityCheckCommand` | commande console | `phil:tenancy:rls:check` : le rôle, la sécurité et la politique de chaque table | Implémenté et testé sur PostgreSQL |
| `Session\SessionActiveTenantStore` | `final readonly class`, `ActiveTenantStore` | Le choix dans la session de l'application | Implémenté et testé |
| `Http\ResolveActiveTenant` | `final readonly class`, écouteur de requêtes | Résout le locataire et y entre ; redirige vers le sélecteur | Implémenté et testé |
| `Http\SwitchActiveTenant` | `final readonly class`, contrôleur | La route `phil_tenancy_switch` ; même origine seulement | Implémenté et testé |
| `Attribute\CurrentTenant` | attribut de paramètre | Demande le locataire actif comme argument de contrôleur | Implémenté et testé |
| `Http\CurrentTenantValueResolver` | `final readonly class`, `ValueResolverInterface` | Résout `#[CurrentTenant]` | Implémenté et testé |
| `Http\ConfiguredHostTenants` | `final readonly class`, `HostTenants`, `HostAliases` | Les hôtes de `phil_tenancy.hosts` et les alias de `host_aliases`, lus par `phil/url` | Implémenté et testé |
| `Http\RedirectHostAlias` | `final readonly class`, écouteur de requêtes | Envoie un alias vers son hôte canonique : `301`, ou `308` pour une méthode autre que `GET` et `HEAD` | Implémenté et testé |
| `Http\ResolveHostTenant` | `final class`, écouteur de requêtes | Entre le locataire de l'hôte avant le pare-feu ; avertit une fois par processus sans hôtes de confiance | Implémenté et testé |
| `Security\TenantScopedUserProvider` | `final readonly class`, `UserProviderInterface`, `PasswordUpgraderInterface` | Refuse le compte d'une session sur un autre locataire que le sien | Implémenté et testé |
| `Security\RememberSignInTenant` | `final readonly class`, écouteur de sécurité | Le locataire de chaque connexion, dans la session | Implémenté et testé |
| `Security\TenantRequestMatcher` | `final readonly class`, `RequestMatcherInterface` | Retient les requêtes de ses locataires, pour un pare-feu de chaque locataire | Implémenté et testé |
| `Security\TenantUserProviders` | `final readonly class`, `UserProviderInterface`, `PasswordUpgraderInterface` | Le fournisseur d'utilisateurs du locataire de la requête, ou celui par défaut ; aucun compte sans l'un ni l'autre | Implémenté et testé |
| `Security\ConfiguredAccountSharing` | `final readonly class`, `AccountSharing` | Les groupes de locataires partagés et les liens entre boutiques | Implémenté et testé |
| `Mailer\TenantTransports` | `final readonly class`, `TransportInterface` | Le transport, l'expéditeur et l'adresse de réponse du locataire sur chaque message | Implémenté et testé |
| `Mailer\TenantMail` | `final readonly class` | Le transport, l'expéditeur et l'adresse de réponse d'un locataire | Implémenté et testé |
| `Mailer\Contract\TenantMailSettings` | port | Les réglages de courrier de chaque locataire | `ConfiguredTenantMailSettings` dans le bundle, ou celui de l'application |
| `Mailer\ConfiguredTenantMailSettings` | `final readonly class`, `TenantMailSettings` | Les réglages de `phil_tenancy.mailer`, vérifiés | Implémenté et testé |
| `Messenger\TenantStamp` | `final readonly class`, `StampInterface` | La référence du locataire dans lequel un message a été réparti, vérifiée | Implémenté et testé |
| `Messenger\TenantMiddleware` | `final readonly class`, `MiddlewareInterface` | Estampille à la répartition, traite dans le locataire de l'estampille à la réception | Implémenté et testé |
| `Console\TenantOption` | `final readonly class`, écouteur de console | L'option `--tenant` de chaque commande | Implémenté et testé |
| `Cache\TenantScopedPool` | `final class`, `AdapterInterface`, `CacheInterface`, `NamespacedPoolInterface`, `TenantActivation` | Un pool limité au locataire actif : le sous-espace de noms du locataire à chaque appel, le pool lui-même sans locataire | Implémenté et testé ; décore `cache.app` et les pools construits dessus |
| `Cache\TenantScopedTagAwarePool` | `final class`, `TagAwareAdapterInterface`, `TagAwareCacheInterface`, `NamespacedPoolInterface`, `TenantActivation` | Les étiquettes préfixées pour le locataire actif ; le sous-espace de noms aussi au-dessus d'un pool nativement à étiquettes | Implémenté et testé ; décore `cache.app.taggable` et les pools à étiquettes |
| `Cache\TenantCacheNamespace` | `final class`, fonctions statiques | Le sous-espace de noms et le préfixe d'étiquette d'un locataire ; les noms réservés | Implémenté et testé |
| `Cache\TenantPools` | `final class` | Le pool de chaque locataire dérivé par `withSubNamespace()`, gardé tant qu'il sert | Implémenté et testé |
| `Cache\TenantTags` | `final class`, fonctions statiques | Les étiquettes d'un élément préfixées à l'enregistrement, retirées à la lecture | Implémenté et testé |
| `Cache\TenantCachePass` | `final readonly class`, passe de compilation | Décore les pools construits sur `cache.app`, sauf ceux qui sont partagés | Implémenté et testé |
| `Twig\TenancyExtension` | `final readonly class`, extension Twig par attributs | `active_tenant()`, `reachable_tenants()` | Implémenté et testé |

## Le bundle

| Type | Sorte | Rôle | État |
|---|---|---|---|
| `PhilTenancyBundle` | `final class`, `AbstractBundle` | Alias d'extension `phil_tenancy` ; la configuration de [configuration.md](configuration.md) | Implémenté et testé |

## Hors du bundle

| Partie | Pourquoi |
|---|---|
| Un adaptateur de locataires atteignables | Sa source (revendications, table d'appartenance, API) appartient à l'application ([extension-points.md](extension-points.md)) |
| La page de sélection | Son rendu, et toute nouvelle authentification, appartiennent à l'application ([resolution.md](resolution.md)) |
| Catalogues, paniers et commandes | Ceux d'un bundle de commerce ; il lit le réseau de boutiques ([hosts-and-shops.md](hosts-and-shops.md)) |
