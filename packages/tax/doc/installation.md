# Installation

## Requirements

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the library declares and is tested on |
| `phil/money` | ^0.1 | `Money` and `Currency`, in which every base and every tax is counted, and `CurrencyMismatch` |

## Installing

```sh
composer require phil/tax
```

The library registers nothing and reads no configuration. `MultiRateTaxCalculator` holds no state and takes no
constructor argument: construct it where needed, or declare it as a service.
