# Options and configured lines

A sellable may have options: a text the buyer types, a choice among pictures, an ink printed as a layer of its own, a
value drawn once the order is paid, an amount the buyer chooses. A catalogue declares them per sellable; an order line
carries the values chosen, its **configuration**, checked by the sellable's options; what the options add to the price
is an adjustment of the line. The same sellable with other values is another line.

| Type | Role |
|---|---|
| `Domain\ValueObject\OptionSet` | The options of a sellable: checks a configuration, gives its supplements |
| `Domain\ValueObject\OptionDefinition` | One option: code, label, kind, required, supplement, and what its kind needs |
| `Domain\Option\OptionKind` | `Text`, `Choice`, `Ink`, `Draw`, `Amount` |
| `Domain\ValueObject\AmountRange` | The amounts a buyer may choose: a minimum of zero or more, a maximum, one currency |
| `Domain\ValueObject\TextConstraints` | A text's length, characters, font, zone, sources and entries |
| `Domain\Option\TextSource` | `Free` (typed), `List` (picked among the entries), `Drawn` (drawn among them after payment) |
| `Domain\ValueObject\PrintZone` | Where a text is printed: a name and a rectangle in millimetres |
| `Domain\ValueObject\OptionChoice` | One choice of a choice or ink option: code, label, image, supplement |
| `Domain\ValueObject\LineConfiguration` | The values of a line, by option code; its fingerprint; its draws |
| `Domain\ValueObject\OptionValue` | One value: chosen, or to draw, then drawn; described by its option |
| `Domain\Exception\InvalidConfiguration` | A configuration the options refuse; `$option` names the option |
| `Application\Port\SellableOptions` | Tagged `phil_commerce.sellable_options`: the options a catalogue declares |
| `Application\LineOptions` | The options of a reference, from the first `SellableOptions` of its type |
| `Application\OptionSupplementProcessor` | Charges what the options add, as `Option` adjustments, before the taxes |
| `Application\Port\OptionDrawer` | Tagged `phil_commerce.option_drawer`: draws a value for one unit |
| `Application\OptionDraws` | Makes the draws of a paid order |
| `Domain\Exception\DrawRefused` | An order not paid yet, or a drawer nobody supports |

## Declaring options

A catalogue implements `SellableOptions` for the types it sells and returns an `OptionSet`, its supplements in the
order's currency:

```php
namespace App\Commerce;

use Phil\CommerceBundle\Application\Port\SellableOptions;
use Phil\CommerceBundle\Domain\Option\TextSource;
use Phil\CommerceBundle\Domain\ValueObject\OptionChoice;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceBundle\Domain\ValueObject\OptionSet;
use Phil\CommerceBundle\Domain\ValueObject\PrintZone;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Domain\ValueObject\TextConstraints;
use Phil\Money\Currency;
use Phil\Money\Money;

final readonly class ProductOptions implements SellableOptions
{
    public function supports(string $type): bool
    {
        return 'product' === $type;
    }

    public function optionsOf(SellableReference $reference, Currency $currency): OptionSet
    {
        return OptionSet::of(
            OptionDefinition::choice('frame', 'Frame', [
                OptionChoice::of('oak', 'Oak', '/images/frames/oak.png'),
                OptionChoice::of('walnut', 'Walnut', '/images/frames/walnut.png', Money::of(400, $currency)),
            ], required: true),
            OptionDefinition::text('caption', 'Caption', TextConstraints::of(
                maxLength: 24,
                characters: "\p{L}\p{N} '.,!?-",
                font: 'Serif Bold',
                zone: PrintZone::of('bottom', 20, 180, 160, 15),
            ), supplement: Money::of(500, $currency)),
            OptionDefinition::ink('ink', 'Ink', [OptionChoice::of('glow', 'Glow in the dark', supplement: Money::of(300, $currency))]),
        );
    }
}
```

| Kind | Declared by | A value is |
|---|---|---|
| `Text` | `OptionDefinition::text(code, label, TextConstraints, required, supplement, drawer)` | A text within its constraints; to draw when its sources include `Drawn` |
| `Choice` | `OptionDefinition::choice(code, label, list<OptionChoice>, required, supplement)` | The code of one of its choices |
| `Ink` | `OptionDefinition::ink(code, label, list<OptionChoice>, required, supplement)` | The code of one of its inks |
| `Draw` | `OptionDefinition::draw(code, label, drawer, supplement)` | Always to draw: never chosen, always set |
| `Amount` | `OptionDefinition::amount(code, label, AmountRange, required)` | Minor units of the range's currency, in digits, within the range |

- **Codes**: an option's code is up to 32 lowercase letters, digits or `_`, starting with a letter; a choice's is 1 to
  64 lowercase letters, digits, `_` or `-`. Each is declared once.
- **A text** holds `minLength` to `maxLength` characters, at most 1000, of its `characters` — a PCRE character class
  without its brackets, matched as Unicode — when typing is allowed. An entry of its list is accepted as written
  when picking is allowed. A text picked or drawn from a list has its entries, and only then; a text that may be
  drawn names its drawer, and only then.
- **The font and the zone** are what a preview draws the text with and where the composer of the print file fits
  it; the bundle checks the length and the characters, not the fit.
- **Supplements** are never negative: a discount is a promotion. What a value adds is its option's supplement plus
  its choice's, or, for an amount, the amount chosen.

## Pay what you want

An amount option lets the buyer set the price on the line: the sellable is priced at zero — or at a fixed part —,
and the amount chosen is added to it as the option's supplement, charged and taxed with the line. The catalogue still
decides the price: it sets the range, and the buyer only picks within it.

```php
use Phil\CommerceBundle\Domain\ValueObject\AmountRange;

OptionSet::of(OptionDefinition::amount('price', 'Your price', AmountRange::of(Money::of(500, $currency), Money::of(5_000, $currency))));

$modifier->add($order, SellableReference::of('ticket', 'concert'), 2, $context, LineConfiguration::of([
    'price' => OptionValue::chosen('1200'), // 12.00, in minor units
]));
```

- **The value is minor units written in digits**: no sign, no decimal point, no leading zero, eighteen digits at
  most. A value outside the range, or one that writes no amount, throws `InvalidConfiguration` naming the option.
- **The maximum is required**: an amount without one would let a buyer reach the largest integer, and a line priced
  past it.
- **Described** with the amount and its currency, `12.00 CAD`, which a receipt shows.
- **Another amount is another line**, its fingerprint differing; the same amount again adds to the line.
- **Required by default**; an optional amount left unset adds nothing — a tip the buyer skips.

## Configuring a line

The buyer's values go to `OrderModifier::add()` as a `LineConfiguration`; the server checks them, and prices come
from the resolvers as for any line:

```php
use Phil\CommerceBundle\Domain\ValueObject\LineConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\OptionValue;

$line = $modifier->add($order, SellableReference::of('product', 'poster-12'), 1, $context, LineConfiguration::of([
    'frame' => OptionValue::chosen('walnut'),
    'caption' => OptionValue::chosen('To Bob, from all of us'),
]));
```

- **Checked by the sellable's options**, `OptionSet::check()`: an option the sellable lacks, a required one left
  unset or a value its option refuses throws `InvalidConfiguration`, before anything changes. Its `$option` names
  the option, for a form to show the error beside its field.
- **Described**: each value is kept with its option's label and what a receipt shows for it — the choice's label, the
  text —, and a value to draw with its drawer. A receipt shows the options as they were sold, whatever the catalogue
  says later.
- **Draw options are always set**, whether the buyer gives `OptionValue::toDraw()` or nothing; a text drawn among its
  entries is asked with `OptionValue::toDraw()`. Optional options left unset are dropped.
- **Two lines of the same sellable with different values are two lines.** A line's reference carries its
  configuration's fingerprint — sixteen hexadecimal digits of the chosen values —, and its `value()` reads
  `product.<fingerprint>:poster-12`. Adding the same values again adds to that line. The adjustments of a configured
  line name that reference, so promotions, taxes and receipts tell the two lines apart.
- **A resolver, a price, a selection is asked with the reference `unconfigured()`**: the configuration belongs to the
  line, not to what is sold. `SellableReference::fromValue()` reads a reference back from its value.

## What the options add

`OptionSupplementProcessor` runs with the other processors, before `TaxOrderProcessor`. For each configured line it
reads the options again through `LineOptions`, as prices are read again, and writes one `Option` adjustment per
option that adds something: labelled with the option's label, its amount the supplement times the line's quantity,
its origin `option:<code>`. `TaxOrderProcessor` taxes a line on its subtotal with these adjustments, after its
promotions.

## Draws after payment

A value to draw is unknown to everyone, the store included, until the order is paid. Once the order's payments are
`Paid`, the application calls `OptionDraws::draw()` and stores the order:

```php
$drawn = $optionDraws->draw($order, $payments->summary($order));   // how many options were drawn
```

- **One draw per unit**: each value left to draw, on each line, is drawn by the first `OptionDrawer` that supports
  the drawer its option names, once for each unit, numbered from 1. A draw is the text a receipt shows and the
  composer of the print file reads, 1 to 1000 characters.
- **Once**: a value drawn is never drawn again, so running it twice draws nothing more.
- **Refused** with `DrawRefused` for an order not paid, or a drawer no service supports.
- **The only change a placed order's lines take**: `Order::recordDraws()` fills a value left to draw, never a chosen
  one, and only on a placed order. The fingerprint leaves the draws out, so the line keeps its reference; a line once
  drawn no longer changes its quantity.
- **How a drawer draws** — dice, an entry of a list —, and whether a buyer may verify the draw, is its own.

## Storage

A configured line keeps its configuration in the `lines` JSON column, under `configuration`: its `version`
(`LineConfiguration::VERSION`, 1) and its `options` by code, each with its `value` (`null` for a value to draw), its
`label`, its `display`, its `drawer` and its `draws`. A line without options has no `configuration` key. An adjustment
on a configured line stores the fingerprint beside the line's type and identifier. A configuration of another version,
or one no code could have written, is refused on reading as a DBAL conversion error.

```json
{"type": "product", "id": "poster-12", "description": "Poster", "quantity": 1, "unit_price": 2500, "currency": "CAD",
 "tax_category": "goods", "configuration": {"version": 1, "options": {
   "caption": {"value": "To Bob, from all of us", "label": "Caption", "display": null, "drawer": null, "draws": []},
   "frame": {"value": "walnut", "label": "Frame", "display": "Walnut", "drawer": null, "draws": []}}}}
```

## On receipts

A receipt copies the order's lines with their configurations. `ReceiptPdf` prints each option below its line, stood
in: its label and its value, with the label `option` (`%s: %s`), or, for a value still to draw, the label `to_draw`
(`drawn after payment`). The supplements are printed with the other adjustments ([receipts.md](receipts.md)).
