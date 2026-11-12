# Requirements

A contest, a shop or a catalogue decides what it asks of the works it receives: which parts must be declared, and,
when it sets one, the most generative AI each of them may hold. `DisclosureRequirement` holds that decision.

```php
use Phil\CreationDisclosure\DisclosureRequirement;
use Phil\CreationDisclosure\GenerativeAi;
use Phil\CreationDisclosure\Part;

// An illustration contest without generative AI.
$contest = DisclosureRequirement::of([Part::Image], GenerativeAi::None);

// A shop that only asks its sellers to declare their text and images.
$shop = DisclosureRequirement::of([Part::Text, Part::Image]);
```

`of()` takes the parts, at least one, kept in the order of `Part` and each once; it throws
`Exception\InvalidDisclosure` without any. The ceiling is optional: without it, any declaration passes, so long as
there is one.

## Checking an entry

`unmet()` lists the required parts a disclosure leaves undeclared, or declares above the ceiling, in the order of
`Part`; `isMetBy()` is `true` when there are none.

```php
$contest->unmet($entry);   // [Part::Image] when the image is undeclared or holds generated content
$shop->isMetBy($product);  // true once the text and the images are declared
```

The views are ordered `None`, `Undeclared`, `Possible`, `Partial`, `Full`; `GenerativeAi::isAtMost()` compares them. An
undeclared part never meets a requirement, whatever its ceiling: a contest at `Undeclared` still asks for a
declaration. Parts the requirement does not name are not looked at.

A form shows each unmet part next to its field; the application decides whether an entry that fails is refused or
held for review.
