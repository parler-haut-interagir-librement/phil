# Options

A template or a design declares options of the core of commerce: a text the buyer types, a choice among pictures, an
ink printed apart, a value drawn after payment, as the options document of `phil/commerce-bundle` describes them. The
module gives them to the core through `SellableOptions`, so `OrderModifier::add()` checks a line's configuration and
the core charges its supplements. An amount the buyer chooses is the one option a template never declares: it belongs on
the order line, and a stored option of that kind is refused when read back.

## Declaring them

```php
use Phil\CommerceBundle\Domain\ValueObject\OptionChoice;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceBundle\Domain\ValueObject\PrintZone;
use Phil\CommerceBundle\Domain\ValueObject\TextConstraints;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\TemplateOption;

$template->declareOptions(
    TemplateOption::of(OptionDefinition::text('name', 'Your name', TextConstraints::of(20, font: 'Display Bold',
        zone: PrintZone::of('nameplate', 40, 300, 200, 40)), supplement: $cad(500))),
    TemplateOption::of(OptionDefinition::ink('ink', 'Ink', [OptionChoice::of('glow', 'Glow in the dark',
        supplement: $cad(300))]), when: ['colour' => ['BK', 'NV']]),
);

$design->declareOptions(
    TemplateOption::of(OptionDefinition::draw('loot', 'Loot', 'loot-gnome'), when: ['version' => ['L']]),
);
```

A `TemplateOption` is an `OptionDefinition` of the core and a **condition**: value codes by axis, the products it is
offered on — the glowing ink on dark colours, the loot draw on the loot box version. Without a condition, every
product of the template or design has it. Each code is declared once per template or design; `declareOptions()`
replaces them all.

## What the core receives

For a product of a group, `TemplateOptions::optionsOf()` gives the template's options, then the design's; a design's
option **replaces** the template's option of the same code, offered or not. Of these, the options whose condition the
product's values meet are kept, in that order. A product of the catalogue outside any group has no options.

The supplements count in the currency of the template's prices. An order in another currency is refused with
`TemplateRefused::currency()`, rather than charged in the wrong currency.

`TemplateOptions` answers the type of `sellable_type`, `product` by default, the type the catalogue sells under; the
core asks the first `SellableOptions` that supports a type, so no other service should declare that type's options.

## Storage

The options are kept as arrays of scalars in the `options` column of their template or design, and read back through
the core's own constructors: an array no code of the module wrote is refused with `TemplateRefused::unreadable()`.
