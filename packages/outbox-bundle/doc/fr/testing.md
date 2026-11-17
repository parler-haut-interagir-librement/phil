<!-- phil-dev source-sha256=d418af505c88bfaa71757dbb2c3f21a04929928e1a11dcd1f08908688873cb7a -->
# Tests

## Les tests du bundle

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau ni réseau : l'entrepôt DBAL réussit les tests de contrat de
phil/outbox sur SQLite en mémoire, et le relais, l'exécuteur et les commandes s'exécutent sur une horloge figée. La
suite fonctionnelle démarre une petite application avec FrameworkBundle et DoctrineBundle sur SQLite. Dans le
monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

La suite d'intégration exécute les tests de contrat et les tests de transaction sur de vrais serveurs, lus dans
l'environnement et ignorés en leur absence : MySQL 8.4 à `PHIL_TEST_MYSQL_URL`, PostgreSQL 17 à
`PHIL_TEST_POSTGRESQL_URL`. Dans le monodépôt, `vendor/bin/phil-dev services up` les démarre dans Docker.

## Le banc d'essai

`tests/Benchmark/DbalRelayBench.php` mesure une centaine d'événements enregistrés dans une seule transaction, puis
relayés en un seul lot de l'entrepôt DBAL sur SQLite en mémoire : `composer bench`.

## Dans les tests d'une application

L'entrepôt `memory` conserve les enregistrements dans le processus, remis à zéro entre les requêtes ; le service
`Relay` les relaie à la demande :

```yaml
# config/packages/test/phil_outbox.yaml
phil_outbox:
    store: memory
```

```php
$service->complete($holder, $set);
self::getContainer()->get(Relay::class)->relay();
```
