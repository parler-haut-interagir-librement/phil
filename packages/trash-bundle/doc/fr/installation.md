<!-- phil-dev source-sha256=9aef2e295f1f71a6ce908488cd4c302c1c60a40398645ff624c094c094505639 -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `ext-mbstring` | toute | Les titres comptés en caractères |
| `doctrine/orm` | ^3.7 | La table de la corbeille |
| `psr/clock`, `psr/event-dispatcher` | ^1.0 | Le moment de la mise à la corbeille, par le service `clock` du conteneur, que FrameworkBundle enregistre une fois `symfony/clock` installé ; les événements |
| `symfony/dependency-injection`, `symfony/config`, `symfony/uid` | ^8.2 | La classe du bundle, sa configuration, les identifiants |
| `phil/resource` | ^0.1 | `ResourceReference`, le type et l'identifiant d'une ressource |
| `symfony/console` | facultatif | `phil:trash:purge` |

```sh
composer require phil/trash-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\TrashBundle\PhilTrashBundle::class => ['all' => true],
];
```

```yaml
# config/packages/phil_trash.yaml
phil_trash:
    retention: 30            # days a trashed resource is kept; 30 when left out
    retention_by_kind:
        page: 90             # pages are kept longer
```

Avec DoctrineBundle, le bundle mappe lui-même son entité ; les migrations de l'application créent `phil_trash_item` :
`id`, `resource_key` (64), `resource_id` (255), `title` (255), `payload` (JSON), `parent` (`kind:id`, nullable),
`trashed_at`, `trashed_by` (nullable), `purge_after`, avec des index sur la ressource et sur `purge_after`.
