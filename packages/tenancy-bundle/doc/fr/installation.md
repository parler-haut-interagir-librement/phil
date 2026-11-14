<!-- phil-dev source-sha256=ba4628de5695a1e61af06227426ff15401396f9e0eb4265c688808a555e921ed -->
# Installation

## Prérequis

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que le bundle déclare et sur lequel il est testé |
| `doctrine/orm` | ^3.5 | `ActiveTenantFilter` est un `SQLFilter` de l'ORM |
| `phil/ddd-bundle` | ^0.1@dev | `InvalidValue` et `DomainError`, que les types de la multilocation lancent et implémentent |
| `doctrine/persistence` | ^4.1 | Le `ManagerRegistry` dans lequel l'activation Doctrine lit les gestionnaires d'entités |
| `symfony/dependency-injection` | ^8.2 | La classe du bundle est un `AbstractBundle` |
| `symfony/config` | ^8.2 | L'arbre de configuration du bundle |
| `symfony/http-foundation`, `symfony/http-kernel`, `symfony/routing` | ^8.2 | Le stockage en session, les écouteurs de requêtes, le changement et l'argument `#[CurrentTenant]` |
| `phil/url` | ^0.1@dev | Les hôtes de `phil_tenancy.hosts`, lus comme l'hôte d'une adresse web |
| `psr/event-dispatcher` | ^1.0 | L'événement `HostTenantResolved` |

| Suggéré | Pour |
|---|---|
| `symfony/console` | L'option `--tenant` de chaque commande |
| `symfony/messenger` | Transmettre le locataire actif à travers les transports |
| `symfony/security-http` | Des sessions limitées au locataire où leurs comptes se sont connectés |
| `symfony/mailer` | Le transport, l'expéditeur et l'adresse de réponse de chaque locataire |
| `twig/twig` | `active_tenant()` et `reachable_tenants()` |

Le bundle n'a besoin d'aucune bibliothèque de fournisseur d'identité : il consomme seulement l'ensemble des locataires
qu'un compte peut atteindre, que l'application lit dans les droits qu'elle détient déjà.

## Installer

Le bundle n'est pas encore publié sur Packagist. Installez-le, ainsi que `phil/ddd-bundle`, à partir du dépôt qui les
contient, par des dépôts Composer de type `path` ou `vcs` :

```json
{
    "repositories": [
        { "type": "path", "url": "../path/to/packages/*" }
    ],
    "require": {
        "phil/tenancy-bundle": "^0.1@dev"
    }
}
```

Une fois publié :

```bash
composer require phil/tenancy-bundle
```

## Enregistrer le bundle

```php
// config/bundles.php
return [
    // …
    Phil\TenancyBundle\PhilTenancyBundle::class => ['all' => true],
];
```

Le bundle de multilocation n'utilise que les classes de domaine de `phil/ddd-bundle` (`InvalidValue`, `DomainError`),
qui sont chargées automatiquement. Enregistrer `PhilDddBundle` n'est pas nécessaire pour la multilocation.

## Configurer

Indiquez au bundle les locataires atteignables et la page de sélection :

```yaml
# config/packages/phil_tenancy.yaml
phil_tenancy:
    reachable_tenants: App\Tenancy\EntitlementsReachableTenants
    request:
        selector_route: tenant_select
        ignored_routes: [login]
```

Quand DoctrineBundle est enregistré, le bundle enregistre le filtre `phil_tenancy` activé sur le gestionnaire
d'entités par défaut : une requête hors de tout locataire ne voit ainsi aucune ligne liée à un locataire, plutôt que
celles de tous les locataires. Le locataire choisi est mémorisé dans la session de l'application. Chaque clé se trouve
dans [configuration.md](configuration.md).

Importez ensuite la route du changement, et ajoutez le *middleware* aux bus dont les messages agissent dans un
locataire ([integrations.md](integrations.md)) :

```php
// config/routes/phil_tenancy.php
return static function (RoutingConfigurator $routes): void {
    $routes->import('@PhilTenancyBundle/config/routes.php');
};
```

Marquez chaque entité appartenant à un locataire avec `#[TenantScoped]` ([doctrine-filter.md](doctrine-filter.md)),
écrivez les locataires atteignables ([extension-points.md](extension-points.md)) et la page de sélection
([resolution.md](resolution.md)).

## Plusieurs gestionnaires d'entités, ou pas de DoctrineBundle

Avec plusieurs gestionnaires d'entités, réglez `doctrine_filter: false` et enregistrez le filtre sur chaque
gestionnaire qui contient des entités liées à un locataire. L'activation règle le locataire sur chaque gestionnaire où
le filtre est activé :

```yaml
doctrine:
    orm:
        entity_managers:
            default:
                filters:
                    phil_tenancy:
                        class: Phil\TenancyBundle\Infrastructure\Doctrine\ActiveTenantFilter
                        enabled: true
```

Sans DoctrineBundle :

```php
$configuration->addFilter(ActiveTenantFilter::NAME, ActiveTenantFilter::class);
$entityManager->getFilters()->enable(ActiveTenantFilter::NAME);
```

et une activation qui vous est propre, qui règle le locataire sur ce gestionnaire d'entités
([integrations.md](integrations.md)).
