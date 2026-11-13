<!-- phil-dev source-sha256=dc2e61ea79f534de247d447d3b2102af12ff3f15508709691f7ee4ab7e87c80e -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` teste chaque partie séparément : les bornes des charges utiles, des options et des définitions,
le lecteur sur chaque forme refusée et le JSON canonique de l'écrivain, le validateur, les conditions sans fonctions, le
catalogue, le moteur sur une horloge figée — exécutions démarrées une fois par événement, délais, filtres,
embranchements, nouvelles tentatives et leur attente croissante, compensation en ordre inverse, baux détenus par un
autre *worker* —, l'action de webhook sur le `MockHttpClient` de Symfony avec sa signature vérifiée, les *handlers* de
Messenger et la planification, et un contrat par magasin exécuté contre les deux magasins, ceux de la base de données
sur SQLite en mémoire.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/automations/tests
```

La suite `tests/Integration` exécute les mêmes contrats de magasin sur un vrai MySQL 8.4 et un vrai PostgreSQL 17, avec
une sauvegarde que seule une de deux connexions gagne ; le test de chaque serveur lit son URL dans
`PHIL_TEST_MYSQL_URL` ou `PHIL_TEST_POSTGRESQL_URL` et est ignoré sans elle :

```sh
PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
vendor/bin/phpunit packages/automations/tests/Integration
```

`tests/Fuzz/definition-reader.php` donne au lecteur du JSON que personne n'a écrit : il produit une définition qui
s'écrit et se relit vers le même JSON, ou il refuse avec `InvalidDefinition`, jamais autre chose. Dans le monodépôt,
Infection maintient la bibliothèque à 100 % des mutants couverts.

## Tester une automatisation

Construisez le moteur sur les magasins en mémoire, une horloge figée et un bus qui garde ses messages, puis avancez
l'horloge :

```php
use Symfony\Component\Clock\MockClock;

$clock = new MockClock('2026-11-13 10:00:00 UTC');
$runs = new InMemoryRunStore();
$engine = new AutomationEngine($catalogue, new InMemoryDefinitions([$definition]), $runs, $bus, $clock);

[$id] = $engine->handle(new OrderPaid('42', 'customer-7'));
$engine->advance($id);              // runs up to the first delay
$clock->modify('+1 day');
$engine->advance($id);              // goes on after it

self::assertSame(RunState::Completed, $runs->get($id)?->state);
```
