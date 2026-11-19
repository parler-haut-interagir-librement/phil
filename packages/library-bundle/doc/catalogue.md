# Catalogue and search

## Titles and records

A title is described by a `Record`:

| Field | Bounds |
|---|---|
| `kind` | `TitleKind::Game`, `Book` or `Other` |
| `name` | 1 to 255 characters |
| `isbn` | An ISBN-10 or ISBN-13, kept as ISBN-13 |
| `creators`, `publisher` | Up to 255 characters |
| `year` | 1000 to 9999 |
| `minPlayers`, `maxPlayers` | 1 to 99, the minimum not above the maximum |
| `minMinutes`, `maxMinutes` | 1 to 10 080 — a week —, the shortest not above the longest |
| `minAge` | 0 to 99 |
| `language` | A language tag: `fr`, `en-CA` |
| `summary` | Up to 5000 characters, lines and tabs kept |

Texts are trimmed, an empty one is none, and a control character is refused — except the line breaks and tabs of a
summary. An ISBN is checked by its check digit, its hyphens and spaces left out, and an ISBN-10 is turned into the
ISBN-13 of the same book, so that either finds it; the record form checks it first with Symfony's `Isbn` constraint. A record that breaks a bound throws `InvalidLibraryData`, whose
message says which.

```php
$title = $catalogue->add(new Record(TitleKind::Game, 'Azul', minPlayers: 2, maxPlayers: 4, minMinutes: 30, maxMinutes: 45, minAge: 8));
$catalogue->describe($title->id, $record);   // replaces the record
$catalogue->title($title->id)->record();
```

## Copies

A copy is one object of a title that is lent:

```php
$copy = $catalogue->addCopy($title->id, 'G-0042', CopyCondition::Good, stockroomItem: null);
$catalogue->assess($copy->id, CopyCondition::Incomplete, 'Two tiles missing.');
$catalogue->link($copy->id, 'item-0042');    // the stockroom item it is; null unlinks it
$catalogue->copies($title->id);              // in the order of their barcodes
$catalogue->copyByBarcode('G-0042');
```

A barcode is 1 to 64 letters, digits, dots, underscores or hyphens, unique in the library. A copy is good, worn,
damaged, incomplete or withdrawn (`CopyCondition`); only a withdrawn copy is not lent. A note is up to 1000 characters.
A title lists at most 500 copies.

## Search

The catalogue's page searches with `TitleSearch`, whose adapter `DbalTitleSearch` reads the titles with DBAL, a long
list read without the ORM, in the order of their names:

```php
$result = $search->search(new SearchCriteria('kiesling', TitleKind::Game, players: 3, minutes: 60, age: 8, availableOnly: true), page: 1, perPage: 25);
$result->rows;     // TitleRow: the record's fields shown, the copies, the copies on the shelf
$result->total;    // the titles found in all
$result->pages();
```

| Criterion | Matches |
|---|---|
| `text` | Words in the name or the creators, case-insensitively, `%` and `_` taken literally; or the ISBN, in either form |
| `kind` | The kind |
| `players` | A title whose players fit the group; a title that gives none fits any group |
| `minutes` | A title whose shortest play fits in so many minutes |
| `age` | A title whose youngest age is at most this one |
| `language` | The language tag |
| `availableOnly` | A title with a copy on the shelf now |

A copy is on the shelf when it is not withdrawn and no loan keeps it now: none active, no reservation whose span holds
this moment. A text is at most 100 characters; a page is 1 to 1000, of 1 to 100 titles.

`phil/search-bundle` is a suggestion only: an application that has a site-wide search indexes the titles there itself,
and keeps this list for the catalogue's own page.
