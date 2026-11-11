<!-- phil-dev source-sha256=3a6851d1b1b7dd5fa8f2c66dfc9daeeb55afb837f290572358f8347c217af423 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau. Elle couvre les attributs et les gardes, la confirmation sur
de vraies entrées de console, le lecteur sur toutes les formes de commandes — services invocables, commandes de niveau
méthode, commandes classiques, paresseuses et tracées —, l'horodatage sur des flux en mémoire, l'écouteur dans une
application de console avec un répartiteur d'événements et une horloge simulée, la commande de vérification,
l'interrupteur Monolog sur de vrais journaliseurs, ainsi que la configuration du conteneur et sa passe de compilation.
La suite fonctionnelle, sous `tests/Functional`, démarre une application avec FrameworkBundle et MonologBundle : une
commande de niveau méthode interdite dans l'environnement de test, une autre confirmée, horodatée, qui affiche ses
journaux de débogage et son sommaire, et la commande de vérification en JSON.

Dans le monodépôt, les tests s'exécutent sur PHP 8.5 et 8.6, et Infection maintient le paquet à 100 % de mutants
couverts.

## Tester une commande protégée

Dans les tests d'une application, l'environnement du noyau est `test` : une commande interdite en `prod` s'y exécute.
Une commande qui demande une confirmation en `test` a besoin de sa réponse ou de `--force-environment` :

```php
$tester = new ApplicationTester($application);
$tester->setInputs(['test']);
$tester->run(['command' => 'app:customers:anonymize'], ['interactive' => true]);

$tester->run(['command' => 'app:customers:anonymize', '--force-environment' => true], ['interactive' => false]);
```

## Le banc d'essai

`tests/Benchmark/ConsoleListenerBench.php` mesure ce que l'écouteur ajoute à une commande, sans et avec horodatages,
ainsi que l'horodatage d'une centaine de lignes écrites d'un coup.

```sh
composer bench -- --filter=ConsoleListenerBench
```
