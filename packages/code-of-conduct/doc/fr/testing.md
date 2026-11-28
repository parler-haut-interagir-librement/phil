<!-- phil-dev source-sha256=669a944713f62e35ac8f1a4836a8ee3c78c064993dd5dc547305025e34068344 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau : les responsables sont
implémentés sous `tests/Support`, les magasins par les implémentations en mémoire du paquet, et le scelleur s'exécute
sur le `InMemoryKms` du composant KeyManagement de Symfony, qui refuse une mauvaise clé, un mauvais contexte ou un
mauvais client comme le ferait un vrai KMS. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants
couverts.
