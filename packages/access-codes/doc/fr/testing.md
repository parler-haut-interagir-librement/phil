<!-- phil-dev source-sha256=05eed5a52db691cec745f199b1e20c3ec3fa98c0d26c234c244660639c9c2ca1 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite `tests/Unit` teste les alphabets et leurs symboles semblables, les formats et leurs bornes, des symboles de
contrôle fixés à la main, les empreintes à clé fixées et leurs clés ordonnées, les lots, le CSV et la liste uniques, les
liens préremplis, `AccessCodes` sur une horloge figée et un limiteur de débit en mémoire, et un seul contrat exécuté sur
les deux magasins, celui de DBAL sur SQLite en mémoire. Dans le monodépôt, Infection tient le paquet à 100 % des mutants
couverts.

La suite `tests/Integration` exécute le même contrat sur un vrai MySQL 8.4 et un vrai PostgreSQL 17, un échange depuis
une autre connexion, et huit processus en concurrence sur un même code ; le test de chaque serveur lit son URL dans
`PHIL_TEST_MYSQL_URL` ou `PHIL_TEST_POSTGRESQL_URL` et est ignoré sans elle :

```sh
PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
vendor/bin/phpunit packages/access-codes/tests/Integration
```

## Fuzzing et banc d'essai

La cible de fuzzing `code-format` donne au lecteur de codes un texte arbitraire : tout ce qu'il lit doit avoir la
longueur et l'alphabet du format et se relire à l'identique, affiché et saisi en minuscules, et il ne lève rien d'autre
que `InvalidCode`.

```sh
composer fuzz -- code-format --budget=60
```

Le banc d'essai `BatchGenerationBench` crée un lot de 100 000 codes sur un magasin en mémoire — environ 0,5 s et 43 Mo
sur un portable récent — et lit et calcule l'empreinte d'un code saisi sous deux clés, environ 9 µs.

```sh
composer bench
```

## Tester du code qui échange

Construisez `AccessCodes` sur un `InMemoryCodeStore`, une `MockClock` et un limiteur de débit sur le `InMemoryStorage` de
Symfony, prenez les codes du lot créé et avancez l'horloge au-delà d'une expiration :

```php
use Symfony\Component\Clock\MockClock;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

$clock = new MockClock('2026-11-13 10:00:00');
$codes = new AccessCodes(
    new InMemoryCodeStore(),
    new CodeHasher(str_repeat('k', 32)),
    new RateLimiterFactory(['id' => 'test', 'policy' => 'no_limit'], new InMemoryStorage()),
    $clock,
);

[$code] = $codes->create('Test', 1, $clock->now()->modify('+1 hour'))->takeCodes();
$clock->sleep(3600);
self::assertSame(RedemptionOutcome::Expired, $codes->redeem($code, 'member-1', 'test')->outcome);
```
