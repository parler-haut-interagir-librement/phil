<!-- phil-dev source-sha256=16122c4a768e6e61342fac5edd0269dfb0b1ed9d20791fe6dbef0734e51c6b5f -->
# Stock et mouvements

## Contenants

Un contenant est un lieu de la réserve qui contient des articles et d'autres contenants : une pièce, une étagère, une
caisse, un sac. Il a un identifiant, un nom, le contenant dans lequel il se trouve — aucun au sommet — et un code
facultatif, le court texte de son étiquette.

```php
$stockroom->addContainer('Warehouse', id: 'warehouse');
$stockroom->addContainer('Shelf B', 'warehouse', 'S-B', 'shelf-b');
$stockroom->addContainer('Crate 3', 'shelf-b', id: 'crate-3');

$stockroom->path('crate-3');            // Warehouse, Shelf B, Crate 3: what the label reads
$stockroom->moveContainer('crate-3', 'truck');  // the crate and all it holds
$stockroom->contents('warehouse', nested: true); // what the warehouse holds, and what its containers hold
```

Un contenant se trouve à 32 niveaux de profondeur au plus (`Stockroom::MAX_DEPTH`). Il ne se trouve jamais en
lui-même ni dans un contenant qu'il renferme : un déplacement qui ferait une boucle est refusé, et un chemin qui boucle
malgré tout — des magasins remplis à la main — est refusé à sa lecture. Un listage imbriqué parcourt 10 000 contenants
au plus et donne 1000 lignes au plus.

## Articles

Un article a un identifiant, un code — unique, le texte de son étiquette et ce que lit un lecteur de codes —, un nom, sa
façon d'être compté, son lieu d'origine — le contenant où il retourne — et son état.

| Suivi | Sens |
|---|---|
| `Tracking::Quantity` | Des pièces interchangeables comptées par lieu : quarante épées en mousse |
| `Tracking::Unit` | Un objet suivi seul, dans un seul lieu à la fois : le projecteur avec son numéro de série |

| État | Peut sortir |
|---|---|
| `ItemCondition::Usable` | Oui |
| `ItemCondition::NeedsRepair` | Non ; il passe quand même d'un contenant à un autre |
| `ItemCondition::Retired` | Non |

Un article perdu n'est pas un état : une perte est un mouvement, et ce qui reste de l'article demeure utilisable.

## Lieux

Le stock est dans un contenant, déployé à un événement ou prêté à un détenteur :

```php
Place::container('crate-3');
Place::event('tavern-scene');
Place::holder('loan-42');
```

Un détenteur est nommé par l'identifiant que l'application donne — un compte, une équipe, un prêt —, jamais par un nom
ni par une adresse courriel. Chaque identifiant et chaque référence de la bibliothèque compte de 1 à 128 lettres,
chiffres, points, tirets bas, deux-points et traits d'union, et commence par une lettre ou un chiffre ; un `@` n'y
entre jamais.

## Mouvements

Chaque méthode écrit un mouvement — ou un par contenant où elle prend — dans le journal et change les soldes.

| Méthode | Genre | De | Vers |
|---|---|---|---|
| `receive($item, $container, $quantity)` | `In` | nulle part | un contenant |
| `issue($item, $place, $quantity, $from)` | `Out` | un contenant, ou les contenants qui le renferment, son lieu d'origine en premier | un événement ou un détenteur |
| `transfer($item, $from, $to, $quantity)` | `Transfer` | un contenant | un autre contenant |
| `lose($item, $place, $quantity)` | `Loss` | n'importe quel lieu | nulle part |
| `giveBack($item, $place, $quantity, $to)` | `Return` | un événement ou un détenteur | le contenant nommé, ou le lieu d'origine de l'article |

Chacune reçoit aussi une référence — l'événement, le prêt, la commande à laquelle le mouvement se rattache — et une
note. Un mouvement est refusé avant que quoi que ce soit change lorsque :

- l'article ou un contenant n'existe pas (`UnknownStock`) ;
- une quantité est inférieure à 1 ou supérieure à 1 000 000, une valeur n'est pas un identifiant, ou les lieux ne
  conviennent pas au genre (`InvalidStock`) ;
- le lieu contient moins de pièces, une unité suivie est reçue alors qu'elle est en stock, ou l'article ne peut pas
  sortir (`InsufficientStock`).

Une unité suivie bouge une à la fois. Une unité perdue puis retrouvée revient avec `receive()`.

Les moments viennent de l'horloge donnée à la réserve et sont conservés en UTC : `$movement->at` est toujours en UTC,
quel que soit le fuseau de l'horloge.

## Lire le stock

```php
$stockroom->holdings($item);          // every place holding pieces, in the order of their keys
$stockroom->available($item);         // the pieces in containers, none when it may not leave
$stockroom->sources($stockroom->item($item)); // the containers holding it, its home first
$stockroom->history($item, 100);      // its movements, the latest first
$stockroom->movementsOf('tavern-scene'); // the movements of an event, a loan or an order
```

`Stockroom` implémente `Contract\StockReader`, le port par lequel un autre paquet lit le stock sans le déplacer.
