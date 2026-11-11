# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `ext-mbstring` | any | Titles counted in characters |
| `doctrine/orm` | ^3.7 | The trash's table |
| `psr/clock`, `psr/event-dispatcher` | ^1.0 | When a resource is trashed, from the container's `clock` service, which FrameworkBundle registers once `symfony/clock` is installed; the events |
| `symfony/dependency-injection`, `symfony/config`, `symfony/uid` | ^8.2 | The bundle class, its configuration, identifiers |
| `phil/resource` | ^0.1 | `ResourceReference`, the kind and identifier of a resource |
| `symfony/console` | optional | `phil:trash:purge` |

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

With DoctrineBundle, the bundle maps its entity itself; the application's migrations create `phil_trash_item`:
`id`, `resource_key` (64), `resource_id` (255), `title` (255), `payload` (JSON), `parent` (`kind:id`, nullable),
`trashed_at`, `trashed_by` (nullable), `purge_after`, with indexes on the resource and on `purge_after`.
