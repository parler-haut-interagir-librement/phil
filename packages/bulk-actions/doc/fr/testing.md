<!-- phil-dev source-sha256=616d1f8e2a747c91b09f5a21454566bc204219df96c8556e36db2dc6f9171137 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau. Dans le monodépôt,
Infection maintient le paquet à 100 % des mutants couverts.

`tests/Support` contient les doublures que tous les tests partagent : des produits en lignes avec une table d'identité,
comme en garde un ORM — une validation vide les produits et monte la version de ceux qui ont changé, une annulation
les oublie —, deux actions, un autorisateur avec des règles, des répartiteurs et une piste d'audit qui enregistrent, et
un magasin d'exécutions qui fait échouer les enregistrements qu'on lui dit.

## Le flux fonctionnel

`tests/Functional/BulkEditFlowTest` parcourt tout le chemin sur 1 200 produits : une sélection par filtre avec une ligne
décochée, le jeton renvoyé sous forme de chaîne, l'aperçu d'un formulaire dont une case est cochée, la confirmation, les
messages consommés par une boucle de *worker* jusqu'à la fin de l'exécution, le jeton refusé une seconde fois, puis une
nouvelle tentative qui n'applique que l'élément en échec.

## Le banc d'essai

`tests/Benchmark/ExecutorBench` mesure une exécution de 5 000 éléments par filtre en lots de 100, et l'aller-retour du
magasin pour une exécution de 10 000 éléments :

```sh
composer bench -- packages/bulk-actions/tests/Benchmark
```

## Tester les actions d'une application

Une application teste ses actions avec les magasins en mémoire et ses propres doublures des ports :

```php
$runs = new InMemoryRunStore();
$confirmer = new BulkConfirmer($targets, $authorizer, new InMemorySelectionStore(), $runs, $dispatcher);
$executor = new BulkExecutor($targets, $authorizer, $runs, $transactions);

$run = $confirmer->start('ana', 'products', 'edit', Selection::identifiers(['p1']), ChangeSet::of(['price' => null]), new DateTimeImmutable());

while ($executor->execute($run->id, new DateTimeImmutable())) {
}

self::assertSame(1, $runs->get($run->id)?->report()->count(ItemOutcome::Succeeded));
```
