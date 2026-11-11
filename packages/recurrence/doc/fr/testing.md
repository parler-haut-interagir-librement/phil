<!-- phil-dev source-sha256=b6384d056904dc2336d99e42928851742ba9018e88eb3d9688db6e00a1a4c629 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `RecurrenceRule`, `WeekdayNum`, `Weekday` | Lecture, écriture, chaque refus et chaque limite de la RFC 5545 |
| `Recurrence` | Les exemples de la RFC 5545 que lit la bibliothèque, changements d'heure, fins, exceptions, dates ajoutées, limites, règles qui ne correspondent à rien |
| `RecurrenceDates` | Chaque forme de `RDATE` et d'`EXDATE` lue et réécrite, chaque refus et chaque limite |

Infection tient la bibliothèque à 100 % de mutants tués.

```sh
vendor/bin/phpunit packages/recurrence/tests
```

## Fuzzing

La cible de *fuzzing* `recurrence-dates` donne au lecteur des lignes `RDATE` et `EXDATE` des entrées que personne n'a
écrites, à partir des germes de `tests/Fuzz/corpus/`. Quoi qu'il lise, il donne une ligne qui, une fois réécrite, se
relit à l'identique, ou refuse la ligne avec l'une de ses propres exceptions. Il ne lève jamais autre chose et n'émet
aucun avertissement. À partir de la racine du monodépôt :

```sh
composer fuzz -- recurrence-dates
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. À partir de la
racine du monodépôt :

```sh
composer bench -- packages/recurrence/tests/Benchmark
```
