<!-- phil-dev source-sha256=a3924c9c07e48eb478a22732faba88b1907c1c228de54b9d33fed469428ab9b6 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `Interval`, `Booking`, `BookingRequest`, `Decision` | Leurs valeurs, chaque refus, les plages qui se touchent |
| Règles | Chaque règle à ses bornes, sur l'horloge de la ressource |
| Disponibilité | Heures hebdomadaires, plages de part et d'autre de minuit, jours fermés, changements d'heure, la borne en jours |
| `Bookable` et `LoadProfile` | Capacité, marges, réservations remplacées, raisons dans l'ordre, créneaux par pas, quantité, règles et bornes |
| `ReminderSchedule` | Décalages, moments, rappel dû, rappels dépassés, bornes |

Infection tient la bibliothèque à 100 % de mutants tués.

```sh
vendor/bin/phpunit packages/reservation/tests
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark` mesurent la vérification d'une demande et la liste des créneaux d'une
semaine parmi des centaines de réservations. À partir de la racine du monodépôt :

```sh
composer bench -- packages/reservation/tests/Benchmark
```
