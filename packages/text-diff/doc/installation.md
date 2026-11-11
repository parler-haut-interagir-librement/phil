# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | Readonly classes, typed constants, `array_all()` |
| `ext-mbstring` | Offsets of changes counted in code points |
| `phil/ujo` | The documents compared block by block, and their rendering |

## Installing

```sh
composer require phil/text-diff
```

It is a library, not a bundle: there is nothing to register. Build a `TextDiff` where differences are shown, once, and
keep it; it holds no state between two comparisons.

```php
use Phil\TextDiff\TextDiff;

$differ = new TextDiff();          // at most 1000 edits searched for
$strict = new TextDiff(maxEdits: 200);
```

In a Symfony application, `phil/text-diff-bundle` registers it as a service, with the `text_diff` and
`text_diff_document` Twig filters and a Stimulus controller that steps through the changes. Without the bundle,
declare it as a service if several classes use it:

```yaml
services:
    Phil\TextDiff\TextDiff: ~
```
