<!-- phil-dev source-sha256=6308dc7cd68aa1b3e51c0dc87ea4de3bd2d9a119dea2283e86633203945b4af3 -->
# Installation

```sh
composer require phil/resource-permissions
```

La bibliothèque exige PHP 8.5 ou une version plus récente, `phil/resource` et `psr/clock`. Le magasin en base de
données exige `doctrine/dbal` 4.5 ou une version plus récente, et son écouteur de schéma `doctrine/orm` 3.7 ou une
version plus récente :

```sh
composer require doctrine/dbal doctrine/orm
```

## Dans une application Symfony

`phil/access-bundle` déclare tous les services ci-dessous à partir de sa configuration. Sans lui, déclarez-les
vous-même :

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

Aucun service ne garde d'état entre les requêtes : une vérification construit son propre état et l'abandonne, donc les
services sont sûrs dans un *worker* de longue durée.
