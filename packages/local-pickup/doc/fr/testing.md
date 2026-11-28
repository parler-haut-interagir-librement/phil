<!-- phil-dev source-sha256=9272ac94ccbea10514d070b76fd44d162fbb84d4904efc2fa55cd7b557395116 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau. Dans le monodépôt,
Infection maintient le paquet à 100 % des mutants couverts.

`tests/Support` contient un magasin ouvert tous les jours de 10 h à 18 h à Montréal, avec deux commandes par plage, et
un enregistreur qui implémente tous les ports et fait échouer un avis quand on le lui demande.

## Le parcours fonctionnel

`tests/Functional/PickupFlowTest` suit tout le parcours : les plages offertes, deux commandes qui remplissent une plage,
les deux marquées prêtes, le *worker* qui envoie les avis, une arrivée manuelle, la remise d'un code numérisé à une
personne désignée, à la caisse d'un kiosque, et l'autre commande remise en stock à la fin de son délai de garde.

## Le fuzzing

`tests/Fuzz/local-pickup-stored.php` lit chaque entrée comme une charge utile numérisée et comme une ligne de ramassage
stockée en JSON. Chacune donne une valeur ou `InvalidPickup`, jamais une autre exception, et une ligne relue réécrit le
même tableau :

```sh
composer fuzz -- local-pickup-stored
```

## Les bancs d'essai

`tests/Benchmark/PickupBench` mesure les plages d'une semaine pendant que 400 commandes sont réservées, la vérification
du code au comptoir et la relecture d'un ramassage stocké :

```sh
composer bench -- packages/local-pickup/tests/Benchmark
```
