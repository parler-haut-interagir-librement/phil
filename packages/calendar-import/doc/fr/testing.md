<!-- phil-dev source-sha256=7307b6d9aba2e3a5768499e3f90f152af500225e26b62994316909461e0372df -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau :

- le lecteur sur des calendriers écrits à la main d'après la RFC 5545, sous `tests/Fixtures` et en ligne : fuseaux par
  nom IANA, nom Windows et `VTIMEZONE`, jours entiers, heures flottantes, récurrences avec exceptions et occurrences
  modifiées, annulations, UID en double, chaque refus et chaque borne ;
- des tests de propriétés avec un germe fixe sur le dépliage : un texte plié à des endroits aléatoires se lit comme le
  texte déplié ;
- le flux par le `MockHttpClient` de Symfony : `200`, `304`, `404`, `500`, une réponse trop grande, une redirection, un
  réseau privé ;
- les instantanés, les différences, les échecs, la politique de rafraîchissement et les surcharges par
  `CalendarImporter`, avec les magasins et la source en mémoire de `tests/Double`, que les tests d'une application
  peuvent copier.

Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

## Le fuzzing

La cible de fuzzing `ics-reader`, `tests/Fuzz/ics-reader.php`, lit des textes que personne n'a écrits : quel que soit
le texte, le lecteur donne des événements qui ont chacun un UID, finissent à leur début ou après, apparaissent une
fois par clé et ne dépassent jamais la borne, ou lance `UnreadableCalendar` ; jamais une autre exception, jamais un
avertissement. Dans le monodépôt :

```sh
composer fuzz -- ics-reader
```

Ses graines sont dans `tests/Fuzz/corpus/ics-reader`.

## Banc d'essai

`tests/Benchmark/ICalendarReaderBench.php` lit un flux de 2 000 événements — dans un fuseau nommé, avec des
descriptions pliées, des catégories, et un événement sur dix qui se répète avec une exception —, comme le fait chaque
rafraîchissement planifié : le temps doit rester proportionnel à la taille du flux.
