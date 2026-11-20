<!-- phil-dev source-sha256=6642c7e6f322a1e131fc7ab301dc9c5660abcf33f0b08c43353cc2739db7a34e -->
# Fichiers d'impression et ordres de production

Une fois une commande payée et ses tirages faits, `PrintProduction::produce('R-42')` compose, avec
`phil/print-compositor`, les fichiers d'impression de chaque produit de la commande qu'un gabarit a généré, et écrit
dans un magasin privé l'**ordre de production** que reçoit l'imprimeur — en JSON pour un programme, en CSV pour un
tableur. Dans un *worker*, le message `ProducePrintFiles` fait la même chose.

La production exige `images_directory`, où les illustrations et les objets sont lus, et `production.directory`, où les
fichiers sont écrits ([Configuration](configuration.md)).

## La disposition d'impression d'un motif

Un motif imprime ses illustrations, telles que le plan les place dans les zones du gabarit. Sa **disposition
d'impression** ajoute ce qui change d'un produit ou d'une ligne de commande à l'autre : chaque élément est dessiné
par-dessus l'illustration de sa zone, comme une couche à part, dans l'ordre déclaré.

```php
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\DiceSlot;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\PrintElement;
use Phil\PrintCompositor\Box;

$objects = static fn (int $table): array => array_combine(range(1, 6), array_map(
    static fn (int $result): string => sprintf('loot/table-%d/%d.png', $table, $result), range(1, 6)));

$design->declarePrintLayout(
    // The fixed objects of the base version and of the convention version.
    PrintElement::image('front', 'chest', 'objects/sword.png', new Box(20, 200, 60, 60), when: ['version' => ['O']]),
    PrintElement::image('front', 'chest', 'objects/empty-purse.png', new Box(20, 200, 60, 60), when: ['version' => ['C']]),
    // Six tables of six objects, one per die of the loot draw.
    PrintElement::dice('front', 'chest', 'loot', array_map(
        static fn (int $table): DiceSlot => DiceSlot::of(new Box(45 * ($table - 1), 270, 40, 40), 6, $objects($table)),
        range(1, 6),
    ), when: ['version' => ['L']]),
    // The tombstone chosen, and the epitaph in the font and rectangle its option imposes.
    PrintElement::choice('back', 'full', 'tomb', new Box(40, 20, 200, 260), ['cross' => 'tombs/cross.png', 'stone' => 'tombs/stone.png']),
    PrintElement::text('back', 'full', 'epitaph', maxSize: 48, minSize: 12),
    // A glowing layer, in an ink of its own, when the glowing ink is chosen.
    PrintElement::image('front', 'chest', 'glow/stars.png', new Box(0, 0, 280, 120), chosen: ['ink' => ['glow']], ink: 'glow'),
);
```

| Élément | Dessine | Ignoré quand |
|---|---|---|
| `image($face, $zone, $path, $box)` | Une image fixe du magasin d'images, contenue dans sa boîte | — |
| `text($face, $zone, $option)` | Le texte d'une option de texte — saisi, choisi ou tiré après le paiement — à la plus grande taille qui tient | La ligne n'a aucune valeur pour l'option |
| `choice($face, $zone, $option, $box, $images)` | L'image du choix fait sur une option de choix ou d'encre | La ligne n'a fait aucun choix, ou un choix sans image |
| `dice($face, $zone, $option, $slots)` | Une image par dé d'un tirage : le résultat de chaque dé choisit l'image de son emplacement | La ligne n'a aucun tirage pour l'option |

Chaque élément accepte :

- `when` : les produits auxquels il s'applique, par valeurs d'axe — `['version' => ['L']]` —, tous les produits quand
  il est vide ;
- `chosen` : les lignes de commande auxquelles il s'applique, par le choix fait sur une option de choix ou d'encre —
  `['ink' => ['glow']]` —, toutes les lignes quand il est vide ;
- `ink` : le fichier d'impression où il va, `colour` par défaut ; chaque encre d'une zone est un fichier à part : une
  couche lumineuse ou une sous-couche blanche parvient donc à l'imprimeur séparément des couleurs.

Les boîtes sont en millimètres depuis le coin supérieur gauche de la zone, et un élément hors de sa zone est refusé
quand le fichier est composé. Une disposition compte au plus 64 éléments, et un élément de dés au plus 64
emplacements.

Un **texte** est imprimé dans la police de son option — `TextConstraints::$font`, un nom de `production.fonts` — et
dans le rectangle de son option — `TextConstraints::$zone` —, à moins que l'élément ne donne sa propre `$font` ou sa
propre `$box` ; un texte sans l'un ni l'autre est refusé. Un texte qui ne tient pas à `$minSize`, ou qui contient un
caractère que sa police n'a pas, est refusé : vérifiez-le là où l'acheteur le saisit, avec le `TextFitter` de
`phil/print-compositor`, avant que la commande ne soit passée.

Rien de ce qu'un acheteur envoie n'est une image : la disposition imprime les images du motif, et le texte que
l'acheteur a saisi ou que le magasin a tiré.

## Les dés

Un élément de dés lit le tirage de son option comme un résultat par emplacement, dans l'ordre, soit en nombres entiers
séparés par des espaces, des virgules, des points-virgules ou des traits d'union — `3 5 2 6 1 4` —, soit à raison d'un
caractère par dé — `352614`. L'ordre de production écrit les dés à raison d'un caractère par dé : `1` à `9`, puis `A`
pour 10 et jusqu'à `K` pour 20 ; un d6 est donc un chiffre, un d10 est hexadécimal et un d20 va au-delà. `DiceCode`
les lit et les écrit.

Les dés vivent dans la configuration de la ligne de commande et dans l'ordre de production ; l'UGS reste celle de la
version coffre à butin.

## Les fichiers d'impression

Pour chaque unité de chaque ligne, chaque zone qui imprime quelque chose — une illustration, ou un élément qui
s'applique — reçoit un fichier par encre : un PNG transparent à la taille de la zone en millimètres et à sa
résolution, écrite dans le fichier.

Un fichier porte le nom d'une empreinte de ce dont il est fait — les octets de chaque image et de chaque police, les
boîtes, les textes, les couleurs, les tailles, la zone —, sous `production.files_prefix` :
`print-files/3f9c…-colour.png`. Ainsi :

- les unités et les commandes qui impriment la même chose partagent un seul fichier, composé une fois ;
- produire de nouveau ne compose que ce qui manque dans le magasin, et n'écrase jamais un fichier avec un autre
  contenu.

## L'ordre de production

Les unités d'une ligne imprimées de la même façon forment une ligne de l'ordre de production ; les unités dont les
tirages diffèrent forment chacune une ligne, puisque chaque coffre à butin a ses propres objets. Une ligne donne :

| Champ | Contient |
|---|---|
| `sku` | L'UGS du produit |
| `quantity` | Les unités imprimées de la même façon |
| `language` | Le code de sa version linguistique, ou `null` |
| `values` | Par code d'axe, dans l'ordre du gabarit : le `code` de la valeur, son `label` dans la locale du produit et le nom de son `supplier` (fournisseur) — la taille, la couleur, la version |
| `options` | Par code d'option : le texte, le code du choix, ou le tirage de l'unité ; pour une option de dés, ses dés |
| `dice` | Par code d'option de dés : un caractère par dé |
| `files` | Chaque fichier d'impression : `face`, `zone`, `ink`, `width` et `height` en millimètres, `dpi` et `path` |

```json
{
    "version": 1,
    "order": "R-42",
    "lines": [
        {
            "sku": "NAQ001FLMBLK",
            "quantity": 1,
            "language": "F",
            "values": {
                "version": {"code": "L", "label": "Loot box", "supplier": null},
                "size": {"code": "M", "label": "M", "supplier": null},
                "colour": {"code": "BLK", "label": "Black", "supplier": "jet black"}
            },
            "options": {"loot": "352614"},
            "dice": {"loot": "352614"},
            "files": [{"face": "front", "zone": "chest", "ink": "colour", "width": 280.0, "height": 350.0, "dpi": 300, "path": "print-files/3f9c….png"}]
        }
    ]
}
```

Le CSV a une rangée par ligne et les colonnes `order`, `sku`, `quantity`, une par axe — son code, puis le nom du
fournisseur entre crochets —, `language`, `dice`, `options` et `files`. Une cellule qu'un tableur lirait comme une
formule commence par une apostrophe.

Les deux sont écrits sous `production.orders_prefix`, au nom de la commande : `production-orders/R-42.json` et
`production-orders/R-42.csv` ; un numéro qui contient d'autres caractères que des lettres, des chiffres, `.`, `_` et
`-` est écrit en hexadécimal sous `hex/`. `orderPath()` donne le chemin. Un ordre de production n'est écrit que si son
contenu a changé, et pas du tout pour une commande sans produit d'un gabarit.

L'imprimeur imprime aujourd'hui par transfert ; l'impression directe sur vêtement (DTG) et les transferts lumineux
prennent les mêmes fichiers, un par encre.

## Dans un *worker*

`ProducePrintFiles` ne porte que le numéro de la commande : le *handler* lit la commande, ses lignes et ce que les
acheteurs ont saisi dans leurs magasins, et les fichiers vont au magasin de production, jamais dans la file. Le
message reste court et ne contient aucun renseignement personnel.

```php
$bus->dispatch(new ProducePrintFiles($order->number));
```

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            Phil\CommerceProductTemplateBundle\Application\Message\ProducePrintFiles: async
```

Avec `production.on_purchase`, le module l'envoie sur `OrderPurchased` du noyau. Les tirages sont faits après le
paiement, par `OptionDraws` du noyau : un *worker* qui traite le message avant qu'ils ne soient enregistrés le
réessaie. Envoyez-le vous-même après `OptionDraws::draw()` pour produire tout de suite.

| Échec | Devient |
|---|---|
| Des tirages restent à faire, ou un magasin qui ne peut pas écrire | `RecoverableMessageHandlingException` : Messenger le réessaie |
| Tout le reste que le module refuse : une commande inconnue, une commande non passée, un texte qui ne tient pas, une image ou une police manquante | `UnrecoverableMessageHandlingException` : jamais réessayé |

Produire est idempotent : un message traité deux fois n'écrit rien de nouveau. Le *claim check* de Messenger 8.2 —
l'option `claim_check` d'un transport — peut garder le transport : aucun message du module n'atteint jamais sa taille.

## Le magasin de production

`ProductionStore` lit et écrit les fichiers par des chemins relatifs au magasin. Avec `production.directory`,
`DirectoryProductionStore` les garde dans un répertoire lisible par son propriétaire seulement — répertoires en mode
0700, fichiers en mode 0600 —, puisqu'ils contiennent ce que les acheteurs ont saisi : gardez-le hors du répertoire
public, et envoyez ses fichiers à l'imprimeur depuis l'application. Un chemin qui pourrait sortir du répertoire est
refusé, et un fichier est écrit en entier.

## Ce qui est refusé

`TemplateRefused` pour une commande inconnue (`unknownOrder()`), une commande non passée (`orderNotPlaced()`), une
ligne dont des tirages restent à faire (`drawsPending()`, dont `isRetryable()` vaut `true`), un produit que son plan ne
donne plus (`notPlanned()`), une police inconnue (`unknownFont()`), une option de texte absente ou sans police ni
rectangle (`textOption()`), un tirage qui ne se lit pas comme ses dés (`draw()`), une image qui manque au magasin
(`missingImage()`), ou un fichier que le compositeur refuse (`printFile()`). Chaque ligne est vérifiée pour ses
tirages avant que quoi que ce soit ne soit écrit.
