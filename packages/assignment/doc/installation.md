# Installation

```sh
composer require phil/assignment
```

The package needs PHP 8.5 or later and nothing else: no extension beyond the ones PHP always has, no framework. The
assigners keep no state between calls, so a Symfony application may declare them as shared services:

```yaml
# config/services.yaml
services:
    Phil\Assignment\RankedRounds: ~
    Phil\Assignment\MinCostAssigner: ~
    Phil\Assignment\ProblemReader: ~
```

To change a setting — the mechanism, the order of rounds, the rank cost, the work budget, the limits — declare the
service with its arguments, or build the object where it is used.
