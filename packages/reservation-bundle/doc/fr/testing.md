<!-- phil-dev source-sha256=1bb554722735138a7e4d3500a52a5d362e95c1d9f7a1249438947480fc0dce5b -->
# Tests

| Aspect | Ce qui est couvert |
|---|---|
| `BookableResource`, `Reservation` | Chaque valeur et son refus, les états et leurs changements, les moments à l'heure de la ressource |
| `Reservations`, `Reminders` | Chaque méthode sur des dépôts en mémoire : le verrou, les refus, les retenues expirées, les événements, les créneaux, les temps tampons de deux voisines additionnés, une personne réservée comme une salle, les rappels envoyés une seule fois |
| Dépôts Doctrine | SQLite en mémoire : ce qui est conservé, les moments écrits et lus en UTC dans un processus d'un autre fuseau horaire, les filtres et l'ordre de chaque requête, le verrou validé et annulé |
| Formulaire, messages, commandes, Messenger, calendrier | Chacun seul, avec les traductions du bundle |
| Le bundle | Ses services et ses options, la passe de compilation, le mappage, et un noyau sur SQLite avec le calendrier, Messenger et un écouteur des événements |
| MySQL 8.4 et PostgreSQL 17 | `tests/Integration` : les tables tirées du mappage, ce qui est conservé, chaque requête, la version d'une réservation, la cascade d'une ressource supprimée, les temps tampons de voisines additionnés, et six processus qui se disputent la dernière unité |

Infection exige du bundle 100 % de mutants tués.

```sh
vendor/bin/phpunit packages/reservation-bundle/tests
```

La suite d'intégration a besoin des serveurs de `phil/dev-tools`, dans Docker ; sans leurs URL, elle est ignorée :

```sh
vendor/bin/phil-dev services up
vendor/bin/phil-dev services test -- packages/reservation-bundle/tests/Integration
vendor/bin/phil-dev services down
```

Elle supprime et crée les deux tables du bundle dans la base de données de test.
