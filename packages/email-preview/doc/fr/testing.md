<!-- phil-dev source-sha256=ade96b85a7484bfe4f3d22c6121be04441b128498d74b87d130e8e3c7d0ea7ec -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau : une classe de test par
règle — un courriel qui la respecte, des courriels qui la violent, et ses limites —, la vérification d'une infolettre
entière qui respecte toutes les règles et d'une qui viole chacune, et la vérification des aperçus de
phil/email-templates-bundle par un `PreviewRenderer` du test. Dans le monodépôt, Infection maintient le paquet à 100 % des
mutants couverts.

## Le banc d'essai

`tests/Benchmark/EmailCheckerBench.php` vérifie une infolettre de 200 articles, environ 300 Ko de HTML, avec les règles par
défaut : le temps doit rester proportionnel à la taille du courriel. Dans le monodépôt :

```sh
composer bench -- --filter=EmailCheckerBench
```
