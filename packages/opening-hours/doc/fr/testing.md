<!-- phil-dev source-sha256=027ee08808d4768942c0e918bd96241c1dc133542e9573cb0ae58c21dfcbab49 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| Heures, plages, jours | Chaque forme écrite, les refus, les plages après minuit, les chevauchements |
| `OpeningHours`, `SpecialHours` | Ouvert ou non aux limites et dans d'autres fuseaux horaires, la veille au soir, les heures spéciales, les prochaines ouvertures, les suites |
| `HoursText`, `OpeningHoursSchema`, `OpeningHoursArray` | Les lignes, les libellés, les spécifications, l'aller-retour et chaque refus |

Infection exige que la bibliothèque tue 100 % des mutants.

```sh
vendor/bin/phpunit packages/opening-hours/tests
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark/` mesurent `nextOpening()` pour des lieux ouverts et toujours fermés,
avec jusqu'à cent périodes spéciales. Depuis la racine du monodépôt :

```sh
composer bench -- packages/opening-hours/tests/Benchmark
```
