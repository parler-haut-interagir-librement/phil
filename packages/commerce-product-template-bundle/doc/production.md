# Print files and production orders

Once an order is paid and its draws are made, `PrintProduction::produce('R-42')` composes the print files of every
product of the order that a template generated, with `phil/print-compositor`, and writes the **production order**
the printer receives — in JSON for a program, in CSV for a spreadsheet — to a private store. In a worker, the message
`ProducePrintFiles` does the same.

Production needs `images_directory`, where the artworks and objects are read, and `production.directory`, where the
files are written ([Configuration](configuration.md)).

## The print layout of a design

A design prints its artworks, as the plan places them in the zones of the template. Its **print layout** adds what
changes from one product or one order line to the next: each element is drawn over the artwork of its zone, as a
layer of its own, in the order declared.

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

| Element | Draws | Skipped when |
|---|---|---|
| `image($face, $zone, $path, $box)` | A fixed image of the store of images, contained in its box | — |
| `text($face, $zone, $option)` | The text of a text option — typed, picked, or drawn after payment — at the largest size that fits | The line has no value for the option |
| `choice($face, $zone, $option, $box, $images)` | The image of the choice made on a choice or ink option | The line made no choice, or one without an image |
| `dice($face, $zone, $option, $slots)` | One image per die of a draw: the result of each die picks the image of its slot | The line has no draw for the option |

Every element takes:

- `when`: the products it applies to, by axis values — `['version' => ['L']]` —, every product when empty;
- `chosen`: the order lines it applies to, by the choice made on a choice or ink option — `['ink' => ['glow']]` —,
  every line when empty;
- `ink`: the print file it goes to, `colour` by default; each ink of a zone is a file of its own, so a
  glow-in-the-dark or a white underbase layer reaches the printer apart from the colours.

Boxes are in millimetres from the top left corner of the zone, and an element outside its zone is refused when the
file is composed. A layout holds at most 64 elements, and a dice element at most 64 slots.

A **text** is printed in its option's font — `TextConstraints::$font`, a name of `production.fonts` — and in its
option's rectangle — `TextConstraints::$zone` —, unless the element gives its own `$font` or `$box`; one without
either is refused. A text that does not fit at `$minSize`, or holds a character its font lacks, is refused: check it
where the buyer types it, with `phil/print-compositor`'s `TextFitter`, before the order is placed.

Nothing a buyer sends is an image: the layout prints the design's images, and the text the buyer typed or the store
drew.

## Dice

A dice element reads the draw of its option as one result per slot, in order, either as whole numbers separated by
spaces, commas, semicolons or hyphens — `3 5 2 6 1 4` — or one character per die — `352614`. The production order
writes the dice one character per die: `1` to `9`, then `A` for 10 and on to `K` for 20, so a d6 is a digit, a d10 is
hexadecimal and a d20 goes past it. `DiceCode` reads and writes them.

The dice live in the configuration of the order line and in the production order; the SKU stays the one of the loot
box version.

## Print files

For each unit of each line, each zone that prints something — an artwork, or an element that applies — gets one file
per ink: a transparent PNG at the zone's size in millimetres and at its resolution, written in the file.

A file is named by a digest of what it is made of — the bytes of each image and font, the boxes, the texts, the
colours, the sizes, the zone —, under `production.files_prefix`: `print-files/3f9c…-colour.png`. So:

- the units and orders that print the same thing share one file, composed once;
- producing again composes only what is missing from the store, and never overwrites a file with other content.

## The production order

Units of a line printed alike make one line of the production order; units whose draws differ make one line each,
since each loot box has its own objects. A line gives:

| Field | Holds |
|---|---|
| `sku` | The SKU of the product |
| `quantity` | The units printed alike |
| `language` | The code of its language version, or `null` |
| `values` | By axis code, in the template's order: the value's `code`, its `label` in the product's locale and its `supplier` name — the size, the colour, the version |
| `options` | By option code: the text, the choice's code, or the draw of the unit; for a dice option, its dice |
| `dice` | By dice option code: one character per die |
| `files` | Each print file: `face`, `zone`, `ink`, `width` and `height` in millimetres, `dpi` and `path` |

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

The CSV has a row per line and the columns `order`, `sku`, `quantity`, one per axis — its code, then the supplier's
name in brackets —, `language`, `dice`, `options` and `files`. A cell a spreadsheet would read as a formula starts
with an apostrophe.

Both are written under `production.orders_prefix`, named after the order: `production-orders/R-42.json` and
`production-orders/R-42.csv`; a number holding other characters than letters, digits, `.`, `_` and `-` is written in
hexadecimal under `hex/`. `orderPath()` gives the path. A production order is written only when its content changed,
and not at all for an order without a product of a template.

The printer prints by transfer today; DTG and glow-in-the-dark transfers take the same files, one per ink.

## In a worker

`ProducePrintFiles` carries the order's number only: the handler reads the order, its lines and what the buyers typed
from their stores, and the files go to the production store, never into the queue. The message stays short and holds
no personal data.

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

With `production.on_purchase`, the module dispatches it on the core's `OrderPurchased`. The draws are made after the
payment, by the core's `OptionDraws`: a worker that handles the message before they are recorded retries it. Dispatch
it yourself after `OptionDraws::draw()` to produce at once.

| Failure | Becomes |
|---|---|
| Draws still to make, or a store that cannot write | `RecoverableMessageHandlingException`: Messenger retries it |
| Anything else the module refuses: an unknown order, an order not placed, a text that does not fit, a missing image or font | `UnrecoverableMessageHandlingException`: never retried |

Producing is idempotent, so a message handled twice writes nothing new. Messenger 8.2's claim check — the
`claim_check` option of a transport — may guard the transport: none of the module's messages ever reaches its size.

## The production store

`ProductionStore` reads and writes the files by paths relative to the store. With `production.directory`,
`DirectoryProductionStore` keeps them in a directory readable by its owner only — directories in mode 0700, files in
mode 0600 —, since they hold what buyers typed: keep it outside the public directory, and send its files to the
printer from the application. A path that could leave the directory is refused, and a file is written whole.

## What is refused

`TemplateRefused` for an unknown order (`unknownOrder()`), an order not placed (`orderNotPlaced()`), a line with
draws still to make (`drawsPending()`, whose `isRetryable()` is `true`), a product its plan no longer gives
(`notPlanned()`), an unknown font (`unknownFont()`), a text option missing or without a font or rectangle
(`textOption()`), a draw that does not read as its dice (`draw()`), an image the store lacks (`missingImage()`), or a
file the compositor refuses (`printFile()`). Every line is checked for its draws before anything is written.
