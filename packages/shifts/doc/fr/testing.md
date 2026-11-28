<!-- phil-dev source-sha256=65dcc9782f04452c6fc8dff079f5632acae16a5919fbc0feac7dc820176f9183 -->
# Tests

## Les tests du paquet

Les tests sont des tests PHPUnit dans `tests/Unit`, lancés depuis la racine du monodépôt avec `composer test`, ou seuls :

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Ils s'exécutent sans noyau, sans base de données et sans réseau. `tests/Fixtures/Shifts` construit les quarts d'un
samedi de congrès — un comptoir d'information, un vestiaire — et leurs instants. Dans le monodépôt, Infection maintient
le paquet à 100 % des mutants couverts.

## Tester les listes d'une application

On teste une liste avec des instants choisis par le test ; la bibliothèque ne lit jamais l'horloge du système :

```php
use Phil\Shifts\Roster\Roster;
use Phil\Shifts\Exception\ShiftRefused;

$roster = Roster::open($shift)->signUp('ada', [], new DateTimeImmutable('2026-11-20T10:00:00Z'));

try {
    $roster->signUp('ada', [], new DateTimeImmutable('2026-11-20T10:01:00Z'));
} catch (ShiftRefused $refused) {
    self::assertSame(ShiftRefused::ALREADY_ON_ROSTER, $refused->reason);
}
```

## La cible de fuzzing

`tests/Fuzz/shift-plan.php` lit avec `ShiftPlan::fromJson()` des documents que personne n'a écrits, vérifie qu'un plan
relu s'écrit à l'identique, puis lit une liste de chaque quart à partir des places du même texte : chaque étape retourne
ou lance une `ShiftsException`. Ses germes se trouvent dans `tests/Fuzz/corpus/shift-plan`.

```sh
composer fuzz -- shift-plan
```

## Le banc d'essai

`tests/Benchmark/RosterBench.php` mesure une liste à ses limites — 1 000 places, une réserve de 100 et 1 000
intéressés — : une inscription dans la réserve, un désistement qui promeut, une attribution de 500 places, la relecture
de la liste depuis son stockage, et les minutes détenues sur 200 listes.

```sh
composer bench
```
