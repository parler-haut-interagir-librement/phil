<!-- phil-dev source-sha256=e5ca756ffa7ea7667222d36b5625c12742068aa10bb16c9b6f6c695f502007dc -->
# Tests

## Les tests du paquet

Les tests sont des tests PHPUnit dans `tests/Unit`, lancés depuis la racine du monodépôt avec `composer test`, ou
seuls :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Ils s'exécutent sans noyau, sans base de données et sans réseau. Les calculs sont vérifiés contre des valeurs
établies à la main, écrites à côté de chaque assertion. `tests/Support` contient les ports en mémoire : `MemoryStock`
(niveaux et seuils, avec le nombre de lectures), `MemoryLowStockStates`, `MemorySubscriptions` (ordonnés comme le
ferait une table), et deux notificateurs qui retiennent ce qu'ils envoient ou échouent sur demande. Dans le monodépôt,
Infection maintient le paquet à 100 % des mutants couverts.

## Les tests de propriétés

`tests/Unit/Replenishment/PropertiesTest.php` tire 500 entrées de germes fixes et vérifie, pour chacune :

- le point de commande moins le stock de sécurité couvre la demande du délai à moins d'une unité près ;
- le point de commande ne baisse jamais quand la demande ou le délai augmente, et suit le stock de sécurité unité pour
  unité ;
- à la quantité économique de commande, commander et détenir coûtent autant, et aucune autre quantité ne coûte moins ;
- quatre fois la demande double la quantité économique de commande ;
- la quantité entière est le plus petit multiple du lot qui atteint ou dépasse la quantité exacte.

## Tester les adaptateurs d'une application

Un adaptateur se teste avec le service qu'il sert et les autres ports en mémoire :

```php
use Phil\InventoryAlerts\LowStock\LowStockMonitor;
use Phil\InventoryAlerts\Stock\StockMovement;

$monitor = new LowStockMonitor(new LogisticsStockSource($repository, $purchaseOrders), $thresholds, $states, $notifier);
$digests = $monitor->record([new StockMovement('m1', 'MUG-BLUE', 'store', -3, new DateTimeImmutable())]);
```

## Le banc d'essai

`tests/Benchmark/AlertsBench.php` mesure les chemins chauds sur 10 000 UGS et emplacements : les propositions d'achat
d'une exécution de nuit sur 25 fournisseurs, et la vérification de stock bas de 10 000 mouvements.

```sh
composer bench
```
