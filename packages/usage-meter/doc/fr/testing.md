<!-- phil-dev source-sha256=dbdf9a5b0e2695ae8dcab8d15e95425b571038ccb4448167132f7ed23914dfbc -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` teste chaque période sur des fenêtres exactes (fins de mois, années bissextiles, semaines ISO à
cheval sur deux années, changements de décalage, moments avant l'ancre et avant l'époque). Un même contrat s'exécute
contre les deux stockages : plafonds, clés par sujet et par compteur, remboursements bornés et idempotents, totaux sur des
plages, purge. Elle teste aussi le
stockage en base de données sur SQLite en mémoire, ses tables, son SQL par plateforme et ses transactions ;
`UsageMeter` sur une horloge figée à travers les frontières de période, les compartiments glissants, les ancres par
sujet et les limites en chaîne ; et les limites lues dans les fonctionnalités, par un vrai `FeatureAccess` de
`phil/commerce-subscription-bundle`.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/usage-meter/tests
```

La suite `tests/Integration` exécute le même contrat sur un vrai MySQL 8.4 et un vrai PostgreSQL 17, avec un remboursement
et une incrémentation depuis deux connexions ; le test de chaque serveur lit son URL dans `PHIL_TEST_MYSQL_URL` ou
`PHIL_TEST_POSTGRESQL_URL` et est ignoré sans elle :

```sh
PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
vendor/bin/phpunit packages/usage-meter/tests/Integration
```

La bibliothèque est tenue à un score de mutation couverte de 100 % par Infection.

## Tester du code qui compte

Construisez le compteur d'utilisation sur un `InMemoryUsageStore` et une horloge figée, comme le `MockClock` de
Symfony, et avancez l'horloge pour franchir une frontière :

```php
use Symfony\Component\Clock\MockClock;

$clock = new MockClock('2026-03-14 10:30:00', 'UTC');
$meter = new UsageMeter($meters, new InMemoryUsageStore(), $clock);

$meter->consume('account-1', 'exports', 3);
$this->expectException(QuotaExceeded::class);
$meter->consume('account-1', 'exports');
```

```php
$clock->modify('2026-04-01 00:00:00');
self::assertSame(0, $meter->usage('account-1', 'exports')->used);
```

Pour tester votre propre stockage, étendez le contrat de la bibliothèque comme le fait `InMemoryUsageStoreTest`, si
les tests de la bibliothèque vous sont accessibles, ou copiez ses cas.
