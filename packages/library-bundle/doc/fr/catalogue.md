<!-- phil-dev source-sha256=85648e07b1914d423700bb6fef9dc24d00d7ce6d5a18c1bd16094954f341d9d7 -->
# Catalogue et recherche

## Titres et fiches

Un titre est décrit par un `Record` :

| Champ | Bornes |
|---|---|
| `kind` | `TitleKind::Game`, `Book` ou `Other` |
| `name` | De 1 à 255 caractères |
| `isbn` | Un ISBN-10 ou un ISBN-13, conservé en ISBN-13 |
| `creators`, `publisher` | Jusqu'à 255 caractères |
| `year` | De 1000 à 9999 |
| `minPlayers`, `maxPlayers` | De 1 à 99, le minimum ne dépassant pas le maximum |
| `minMinutes`, `maxMinutes` | De 1 à 10 080 — une semaine —, le plus court ne dépassant pas le plus long |
| `minAge` | De 0 à 99 |
| `language` | Une étiquette de langue : `fr`, `en-CA` |
| `summary` | Jusqu'à 5000 caractères, sauts de ligne et tabulations conservés |

Les textes sont rognés, un texte vide équivaut à aucun, et un caractère de contrôle est refusé — sauf les sauts de
ligne et les tabulations d'un résumé. Un ISBN est vérifié par sa clé de contrôle, ses traits d'union et ses espaces
étant omis, et un ISBN-10 est converti en l'ISBN-13 du même livre, de sorte que l'un ou l'autre le trouve ; le
formulaire de la fiche le vérifie d'abord avec la contrainte `Isbn` de Symfony. Une fiche qui enfreint une borne lève
`InvalidLibraryData`, dont le message dit laquelle.

```php
$title = $catalogue->add(new Record(TitleKind::Game, 'Azul', minPlayers: 2, maxPlayers: 4, minMinutes: 30, maxMinutes: 45, minAge: 8));
$catalogue->describe($title->id, $record);   // replaces the record
$catalogue->title($title->id)->record();
```

## Exemplaires

Un exemplaire est un objet d'un titre qui est prêté :

```php
$copy = $catalogue->addCopy($title->id, 'G-0042', CopyCondition::Good, stockroomItem: null);
$catalogue->assess($copy->id, CopyCondition::Incomplete, 'Two tiles missing.');
$catalogue->link($copy->id, 'item-0042');    // the stockroom item it is; null unlinks it
$catalogue->copies($title->id);              // in the order of their barcodes
$catalogue->copyByBarcode('G-0042');
```

Un code à barres compte de 1 à 64 lettres, chiffres, points, tirets bas ou traits d'union, et est unique dans la
bibliothèque. Un exemplaire est bon, usé, endommagé, incomplet ou retiré (`CopyCondition`) ; seul un exemplaire retiré
n'est pas prêté. Une note compte jusqu'à 1000 caractères. Un titre liste 500 exemplaires au plus.

## Recherche

La page du catalogue cherche avec `TitleSearch`, dont l'adaptateur `DbalTitleSearch` lit les titres avec DBAL, une
longue liste lue sans l'ORM, dans l'ordre de leurs noms :

```php
$result = $search->search(new SearchCriteria('kiesling', TitleKind::Game, players: 3, minutes: 60, age: 8, availableOnly: true), page: 1, perPage: 25);
$result->rows;     // TitleRow: the record's fields shown, the copies, the copies on the shelf
$result->total;    // the titles found in all
$result->pages();
```

| Critère | Correspond à |
|---|---|
| `text` | Des mots du nom ou des auteurs, sans égard à la casse, `%` et `_` pris littéralement ; ou l'ISBN, sous l'une ou l'autre forme |
| `kind` | Le genre |
| `players` | Un titre dont les joueurs conviennent au groupe ; un titre qui n'en donne aucun convient à tout groupe |
| `minutes` | Un titre dont la partie la plus courte tient en autant de minutes |
| `age` | Un titre dont l'âge minimal est au plus celui-ci |
| `language` | L'étiquette de langue |
| `availableOnly` | Un titre dont un exemplaire est sur l'étagère maintenant |

Un exemplaire est sur l'étagère lorsqu'il n'est pas retiré et qu'aucun prêt ne le retient maintenant : aucun actif,
aucune réservation dont la période contient ce moment. Un texte compte 100 caractères au plus ; une page va de 1 à 1000,
de 1 à 100 titres.

`phil/search-bundle` n'est qu'une suggestion : une application qui a une recherche pour tout le site y indexe elle-même
les titres, et garde cette liste pour la page du catalogue.
