# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | Readonly classes, typed constants, `array_any()`, `new` without parentheses |
| `phil/fonts` | The embedding permissions of a font, read from its `OS/2` table |

The library has no other dependency: no binary, no service, no PDF reader.

## Installing

```sh
composer require phil/print-preflight
```

It is a library, not a bundle: there is nothing to register. Build a `Preflight` where a document is checked:

```php
use Phil\PrintPreflight\Preflight;
use Phil\PrintPreflight\PrintProfile;

$preflight = new Preflight(new PrintProfile(pageMultiple: 4));
$report = $preflight->check($document);
```

A `Preflight` holds no state between two checks: one instance, built from the printer's profile, can serve every
document sent to that printer. An application that works with several printers keeps one profile per printer.
