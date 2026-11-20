<!-- phil-dev source-sha256=a858cdababdc7f99b90ccc9a63d2b39f5114d3a657c3658940e1e7069da0b98a -->
# phil/tenancy-bundle

**Le locataire dans lequel un compte agit en ce moment, changé instantanément et imposé dans chaque requête.**

phil/tenancy-bundle gère le locataire actif d'une application Symfony multilocataire, que le compte le choisisse
parmi les locataires qu'un fournisseur d'identité externe lui laisse atteindre ou que l'hôte de la requête le décide. Il
valide des références de locataire opaques et résout le locataire dans lequel agit le compte
courant, à partir du choix qu'il a mémorisé et des locataires qu'il peut atteindre. Un changement de locataire est
vérifié contre cet ensemble, sans aller-retour vers le fournisseur d'identité. Un filtre Doctrine garde les requêtes à
l'intérieur du locataire actif et échoue en mode fermé. Le bundle ne conserve pas le locataire actif dans un jeton ni
dans la session du fournisseur d'identité. Il ne décide pas d'où viennent les droits d'un compte, et ne produit pas le
rendu de la page de sélection.

Il s'adresse aux applications où un compte appartient à plusieurs locataires, par exemple deux organisations ou deux
boutiques. Le fournisseur d'identité sait quels locataires un compte peut atteindre, et sa session couvre toutes les
applications qui la partagent. Le locataire actif appartient à une seule application : il y reste donc. Le changement
continue de fonctionner quand le fournisseur est en panne, et la résolution ne devine jamais : avec plusieurs
locataires et aucun choix utilisable, c'est le compte qui choisit.

C'est un bundle Symfony construit sur Doctrine ORM et `phil/ddd-bundle`. Il branche le choix mémorisé dans la session
de l'application, un écouteur de requêtes qui envoie au sélecteur un compte qui doit choisir, un changement limité à la
même origine, un argument de contrôleur `#[CurrentTenant]`, le locataire transmis à travers les transports de
Messenger, une option `--tenant` sur chaque commande, et deux fonctions Twig. Plusieurs sites peuvent vivre dans une
application : chaque hôte, et chacun de ses alias, sert son locataire, les boutiques par domaine partagent une caisse
et un compte client, une session ouverte sur un locataire n'est pas ouverte sur un autre, un locataire peut connecter
ses membres par son propre pare-feu et les charger depuis son propre fournisseur d'utilisateurs, et le courrier de
chaque locataire part par son propre transport et son propre expéditeur. Chaque locataire lit et écrit sa propre part de `cache.app` et des pools construits
dessus, étiquettes comprises. Messenger, le composant Console, Twig, le composant Security, le composant Mailer et le
composant Cache sont facultatifs. L'application fournit les locataires atteignables par le port `ReachableTenantsProvider`, et le
stockage du choix est lui aussi un port.

## État

| Partie | État |
|---|---|
| `TenantId`, `ReachableTenants` | Implémenté et testé |
| `ActiveTenantRule`, `Resolution`, `Outcome`, `TenantNotReachable`, `NoActiveTenant` | Implémenté et testé |
| `TenantScoped`, `ActiveTenantFilter`, `MisconfiguredTenantScope` | Implémenté et testé ; le filtre est enregistré activé |
| `ActiveTenant`, `TenantContext`, `TenantActivation`, `DoctrineTenantActivation` | Implémenté et testé |
| `SessionActiveTenantStore` | Implémenté et testé ; le stockage par défaut |
| `ResolveActiveTenant`, `SwitchActiveTenant`, `#[CurrentTenant]` | Implémenté et testé |
| `TenantMiddleware`, `TenantStamp`, `TenantOption`, `TenancyExtension` | Implémenté et testé ; enregistrés quand leur composant est installé |
| `HostTenants`, `ConfiguredHostTenants`, `ResolveHostTenant`, `HostTenantResolved` | Implémenté et testé ; enregistrés avec `hosts` |
| `HostAliases`, `RedirectHostAlias` | Implémenté et testé ; enregistrés avec `host_aliases` |
| `ShopNetwork`, `OrderOrigin`, `ShopContext` | Implémenté et testé |
| `AccountSharing`, `ConfiguredAccountSharing`, `TenantScopedUserProvider`, `RememberSignInTenant` | Implémenté et testé ; désactivés tant que `authentication` n'est pas activé |
| `TenantRequestMatcher`, `TenantUserProviders` | Implémenté et testé ; enregistrés avec `authentication.tenant_firewalls` et `tenant_user_providers` |
| `TenantMailSettings`, `ConfiguredTenantMailSettings`, `TenantMail`, `TenantTransports` | Implémenté et testé ; enregistrés avec les réglages `mailer` |
| `TenantScopedPool`, `TenantScopedTagAwarePool`, `TenantCacheNamespace`, `TenantCachePass` | Implémenté et testé ; `cache.app` et les pools construits dessus limités au locataire quand `symfony/cache` est installé |
| `ReachableTenantsProvider` | Port ; l'application l'implémente |
| `TenantPlacement`, `Placement` | Port et objet-valeur ; un bundle de base de données ou l'application implémente le port |
| `PhilTenancyBundle` | Configuration `phil_tenancy` |

## Documentation

- [Installation](doc/fr/installation.md) : les prérequis, l'installation, l'enregistrement et la configuration du
  bundle, la route du changement, et plusieurs gestionnaires d'entités.
- [Configuration](doc/fr/configuration.md) : chaque clé, ce que chacune enregistre, ce que l'application écrit
  encore, et les constantes du bundle.
- [Intégrations](doc/fr/integrations.md) : le contexte du locataire et ses activations, l'écouteur de requêtes, le
  changement, `#[CurrentTenant]`, Messenger, la console et Twig.
- [Hôtes et boutiques](doc/fr/hosts-and-shops.md) : le locataire de chaque hôte, les alias d'un hôte et leur
  redirection, le réseau de boutiques, la boutique d'une requête et l'origine d'une commande.
- [Authentification par locataire](doc/fr/authentication.md) : les sessions limitées au locataire où leurs comptes se
  sont connectés, les comptes partagés, un pare-feu pour chaque locataire et un fournisseur d'utilisateurs pour chaque
  locataire.
- [Courrier par locataire](doc/fr/mail.md) : le transport, l'expéditeur et l'adresse de réponse de chaque locataire.
- [Cache par locataire](doc/fr/cache.md) : `cache.app` et les pools construits dessus limités au locataire actif, un
  sous-espace de noms et des étiquettes préfixées pour chaque locataire, les pools que tous partagent, et le
  comportement dans un *worker*.
- [Le locataire actif](doc/fr/active-tenant.md) : pourquoi le locataire actif appartient à l'application, `TenantId`
  et ses refus, `ReachableTenants`, le service `ActiveTenant` et une requête de bout en bout.
- [Résolution et changement](doc/fr/resolution.md) : la table de résolution, la règle du changement, `Resolution`,
  `Outcome` et les règles du sélecteur.
- [Le filtre Doctrine](doc/fr/doctrine-filter.md) : `#[TenantScoped]`, le SQL qu'ajoute le filtre, l'échec en mode
  fermé, le travail entre locataires, les erreurs d'association et les limites du filtre.
- [Sécurité de ligne](doc/fr/row-level-security.md) : la sécurité de ligne de PostgreSQL derrière le filtre, la
  variable de session de chaque connexion, les politiques générées, le rôle et la commande de vérification.
- [Points d'extension](doc/fr/extension-points.md) : les ports méthode par méthode, l'emplacement des données d'un
  locataire parmi eux, leurs invariants et des exemples d'adaptateurs.
- [Tests](doc/fr/testing.md) : comment le bundle est testé, comment lancer ses tests, et comment une application teste
  ses adaptateurs et son isolement.
- [Référence](doc/fr/reference.md) : l'API publique par couche, une ligne par type, avec son état.
- [Mise à niveau](doc/fr/upgrading.md) : de la 0.1 à la 0.2, `flush()` avant un changement de locataire, les entités
  relues après `within()`, et `CrossTenantWrite`.

## Démarrage rapide

```bash
composer require phil/tenancy-bundle
```

```yaml
# config/packages/phil_tenancy.yaml
phil_tenancy:
    reachable_tenants: App\Tenancy\EntitlementsReachableTenants # your adapter
    request:
        selector_route: tenant_select
```

```php
#[ORM\Entity]
#[TenantScoped] // the field `tenant` holds the tenant reference
class Order { /* … */ }

// Every query of the request now sees the active tenant's orders only.
public function orders(#[CurrentTenant] TenantId $tenant, OrderRepository $orders): Response { /* … */ }
```

## Prérequis

PHP 8.5 ou plus récent ; `doctrine/orm` 3.5 ou plus récent ; `phil/ddd-bundle` 0.1 ; `symfony/config`,
`symfony/dependency-injection`, `symfony/http-foundation`, `symfony/http-kernel` et `symfony/routing` 8.2 ou plus
récent ; `phil/url` 0.1 ; `psr/event-dispatcher` 1.0. `symfony/console`, `symfony/messenger`, `symfony/security-http`,
`symfony/mailer` et `twig/twig` sont suggérés.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
