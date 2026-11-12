<!-- phil-dev source-sha256=0bc1265a9790e6eb77b1e319550ced6c66e1377444c020479aecd1d666a56445 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau. Dans le monodépôt,
Infection maintient le paquet à 100 % des mutants couverts.

| Domaine | Ce qui est couvert |
|---|---|
| Les valeurs | Des plages occupées en UTC, les chevauchements et les coupes ; les fenêtres et leurs bornes ; les plages jointes, provisoires ou fermes, et leur maximum ; les connexions, les identifiants de connexion et les événements refusés à chaque borne ; des identifiants de connexion jamais sérialisés ni affichés ; les messages des échecs |
| `CalendarSync` et les messages | Les plages occupées lues, jointes et stockées ; une lecture échouée qui laisse le dépôt tel quel ; les événements écrits et retirés ; les connexions et les adaptateurs inconnus ; la fenêtre d'un message ; un message d'événement qui écrit ou retire |
| Le lecteur iCalendar | Les heures en UTC, dans leur fuseau, dans un fuseau Windows, dans le fuseau du calendrier ; les journées entières ; les événements annulés, transparents et provisoires ; les règles à travers un changement d'heure avec des occurrences ajoutées, retirées et modifiées ; les plages libre/occupé ; le repliement, les fins de ligne, les alarmes et une marque d'ordre des octets ; chaque refus et chaque borne, à son maximum et un cran au-delà |
| Le rédacteur iCalendar | Un événement horaire en UTC, une journée entière relue, des textes échappés, des lignes repliées entre les caractères |
| Les adaptateurs | Chaque requête — méthode, adresse, en-têtes, corps —, chaque réponse lue, chaque statut d'erreur, des identifiants de connexion refusés, des réponses illisibles, des pages bornées, une redirection non suivie, un réseau privé refusé, une réponse trop grande, une erreur de transport qui cache l'adresse |

Les adaptateurs sont testés contre le `MockHttpClient` de Symfony, avec des adresses fixes : aucun test ne résout un nom
ni n'ouvre une connexion.

## Banc d'essai

`tests/Benchmark/ICalendarReaderBench.php` lit des flux de 100, de 1 000 et de 5 000 événements — un dixième d'entre eux
des règles hebdomadaires avec une date retirée, chacun avec une description sautée — et joint leurs plages sur
60 jours :

```sh
composer bench -- packages/calendar-sync/tests/Benchmark
```

## Fuzzing

La cible `calendar-sync-icalendar` donne au lecteur iCalendar des textes que personne n'a écrits, à partir des germes de
`tests/Fuzz/corpus/calendar-sync-icalendar`. Chacun donne des plages dans la fenêtre, chacune finissant après son début,
10 000 au plus, ou `InvalidCalendarData`, jamais une autre exception.

```sh
composer fuzz -- calendar-sync-icalendar
```
