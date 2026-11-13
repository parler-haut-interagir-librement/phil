# Installation

```sh
composer require phil/resource-permissions
```

The library needs PHP 8.5 or later, `phil/resource` and `psr/clock`. The database store needs `doctrine/dbal` 4.5 or
later, and its schema listener `doctrine/orm` 3.7 or later:

```sh
composer require doctrine/dbal doctrine/orm
```

## In a Symfony application

`phil/access-bundle` declares every service below from its configuration. Without it, declare them yourself:

```php
// config/services.php
use Phil\ResourcePermissions\Budget;
use Phil\ResourcePermissions\Contract\RelationCheck;
use Phil\ResourcePermissions\Contract\TupleStore;
use Phil\ResourcePermissions\Dbal\DbalTupleStore;
use Phil\ResourcePermissions\Dbal\TupleSchemaListener;
use Phil\ResourcePermissions\Model\Model;
use Phil\ResourcePermissions\RelationChecker;
use Phil\ResourcePermissions\RelationWriter;
use Phil\ResourcePermissions\Tree\TreePermissions;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(Model::class)->factory([App\Access\AppModel::class, 'create']);
    $services->set(DbalTupleStore::class)->args([service('doctrine.dbal.default_connection'), service('clock')]);
    $services->alias(TupleStore::class, DbalTupleStore::class);
    $services->set(Budget::class);
    $services->set(RelationChecker::class)->args([service(Model::class), service(TupleStore::class), service(Budget::class)]);
    $services->alias(RelationCheck::class, RelationChecker::class);
    $services->set(RelationWriter::class)->args([service(Model::class), service(TupleStore::class)]);
    $services->set(TreePermissions::class)->args([service(TupleStore::class)]);
    $services->set(TupleSchemaListener::class)
        ->args([service('doctrine.dbal.default_connection'), service(DbalTupleStore::class)])
        ->tag('doctrine.event_listener', ['event' => 'postGenerateSchema']);
};
```

Every service keeps nothing between requests: a check builds its own state and drops it, so the services are safe in a
long-running worker.
