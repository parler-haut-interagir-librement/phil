# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | Readonly classes, typed constants, `new` without parentheses |
| `ext-mbstring` | The length of labels in characters |
| `phil/money` | Prices and supplements, in minor units of one currency |

## Installing

```sh
composer require phil/commerce-product-template
```

It is a library, not a bundle: there is nothing to register. Every type is a value that never changes once built,
and `VariantPlanner` holds no state between two calls, so one instance serves every plan.

A Symfony application that stores templates and designs and generates its catalogue from them installs
`phil/commerce-product-template-bundle` beside it.
