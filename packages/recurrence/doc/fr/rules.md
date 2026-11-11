<!-- phil-dev source-sha256=1b41c117fedc7e5296d89afe207bd81f2adfeef726214128144d28d99590e702 -->
# Règles

`RecurrenceRule::parse()` lit une règle telle que l'écrit la RFC 5545, avec ou sans `RRULE:`, ses parties dans
n'importe quel ordre et n'importe quelle casse. `(string) $rule` la réécrit, ses parties dans un ordre fixe, sans celles
qui ont leur valeur par défaut.

```php
$rule = RecurrenceRule::parse('rrule:freq=weekly;interval=2;byday=mo,we;until=20261231T235959Z');
(string) $rule;   // "FREQ=WEEKLY;INTERVAL=2;UNTIL=20261231T235959Z;BYDAY=MO,WE"
```

| Partie | Contient |
|---|---|
| `FREQ` | `YEARLY`, `MONTHLY`, `WEEKLY` ou `DAILY` |
| `INTERVAL` | Toutes les tant de périodes, 1 par défaut |
| `COUNT`, `UNTIL` | Tant d'occurrences, ou le dernier moment où une occurrence peut commencer : un jour (`20261231`), une heure UTC (`…T235959Z`), ou une heure flottante lue dans le fuseau horaire donné à `parse()` |
| `BYDAY` | Des jours de la semaine, avec leur rang dans le mois ou l'année pour les règles mensuelles et annuelles : `MO`, `2TU`, `-1FR` |
| `BYMONTHDAY` | Des jours du mois, comptés depuis la fin s'ils sont négatifs : `1`, `15`, `-1` |
| `BYMONTH` | Des mois, de 1 à 12 |
| `BYSETPOS` | Les rangs, dans chaque période, des jours que gardent les autres parties : `-1`, le dernier |
| `WKST` | Le jour où commencent les semaines, lundi par défaut |

`RecurrenceRule::of()` en construit une à partir de ses parties.

## Ce qui est vérifié

Comme le demande la RFC 5545, une règle est refusée avec `InvalidRule` pour : un intervalle ou un décompte inférieur
à 1 ; à la fois un décompte et une fin ; un jour de fin sans sa fin ; un rang dans le mois ou l'année dans une règle
hebdomadaire ou quotidienne, au-delà de 5 dans une règle mensuelle, au-delà de 53 ou égal à 0 où que ce soit ; des jours
du mois dans une règle hebdomadaire, au-delà de 31 ou égaux à 0 ; des mois hors de 1 à 12 ; des positions au-delà de
366 ou égales à 0, ou sans autre partie `BY`. Les fréquences horaires et plus fines, `BYHOUR`, `BYMINUTE`, `BYSECOND`,
`BYWEEKNO` et `BYYEARDAY` ne sont pas lues : `InvalidRule` les nomme.

`of()` et `parse()` refusent les mêmes règles, pour que toute règle écrite en texte se relise : un intervalle ou un
décompte au-dessus de `RecurrenceRule::MAX_NUMBER`, 9999, les quatre chiffres que lit `parse()` ; une fin hors des
années 1 à 9999, en UTC pour une heure de fin et telle quelle pour un jour de fin, puisque `UNTIL` écrit son année avec
quatre chiffres. Une règle que construit un formulaire ou une importation peut donc être stockée puis hydratée à
nouveau.
