<!-- phil-dev source-sha256=eada2f62100693ca03adffca70bfcba28d4130b7530ec230a500ebff2d5cf654 -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` teste les jours à travers les années, les jours bissextiles et les fuseaux horaires ; chaque règle
des horaires et les périodes qu'ils découpent ; les habitudes et les validations à leurs bornes ; les séries et la force
sur des habitudes quotidiennes, hebdomadaires et de jours de semaine avec des jours faits, partiels, sautés et gelés ;
`Habits` sur une horloge figée, avec les événements qu'il répartit ; et un seul contrat exécuté contre les dépôts en
mémoire et sur SQLite en mémoire. `tests/Integration` exécute le même contrat contre MySQL et PostgreSQL.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/habits/tests/Unit
```

Un test qui a besoin d'un vrai serveur lit son URL dans une variable d'environnement et est ignoré sans elle :
`PHIL_TEST_MYSQL_URL` et `PHIL_TEST_POSTGRESQL_URL`. `phil-dev services` démarre MySQL 8.4 et PostgreSQL 17 dans Docker
et affiche les variables :

```sh
vendor/bin/phil-dev services test
vendor/bin/phpunit packages/habits/tests/Integration
```

Les cibles de fuzzing `schedule-rule` et `day` tiennent les analyseurs à leur contrat ; `tests/Benchmark` mesure le
progrès d'une habitude tenue pendant dix ans. Dans le monodépôt, Infection tient la bibliothèque à 100 % des mutants
couverts.

## Tester du code qui emploie des habitudes

Construisez `Habits` sur les dépôts en mémoire et une horloge figée, comme le `MockClock` de Symfony, et avancez
l'horloge pour passer un jour :

```php
use Symfony\Component\Clock\MockClock;

$clock = new MockClock('2026-11-02 12:00:00', 'UTC');
$habits = new Habits(new InMemoryHabitStore(), new InMemoryCheckInStore(), $clock);

$habits->start('habit-1', 'member-1', 'Read', Schedule::daily(), new DateTimeZone('UTC'));
$habits->checkIn('habit-1');
$clock->modify('+1 day');

self::assertSame(2, $habits->checkIn('habit-1')->streak->current);
```
