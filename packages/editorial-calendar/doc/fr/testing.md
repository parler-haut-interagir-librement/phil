<!-- phil-dev source-sha256=eca2d149c1eb2ea809c9938ac963d503b39c4fc2a0d2f05fae96f9bc6dc835dc -->
# Tests

## Les tests du paquet

`tests/Unit` couvre les éléments, leurs vérifications et leur flux de travail, les auteurs par identifiant, les lots,
les moments en UTC et leur ordre ; le calendrier, ses identifiants, ses lots appliqués en entier ou refusés en entier,
les lots échus et manuels ; les vues, leurs jours dans un fuseau horaire lors d'un changement d'heure, leurs semaines à
partir de chaque premier jour, leurs entrées et leur ordre, les transitions lues par le port ; la revue et ses règles
à leurs bornes ; et le flux iCalendar, son échappement et son repliement.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/editorial-calendar/tests
```

Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

## Les bancs d'essai

`tests/Benchmark/CalendarViewBench.php` montre un mois, le passe en revue et exporte une année en iCalendar, sur un
calendrier de 5 000 éléments, dont un cinquième dans 500 lots. Chacun coûte un temps qui croît avec les éléments,
jamais avec les éléments multipliés par les lots.

```sh
vendor/bin/phil-dev bench -- --filter=CalendarViewBench packages/editorial-calendar/tests/Benchmark
```
