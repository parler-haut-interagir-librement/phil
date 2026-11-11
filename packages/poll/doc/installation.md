# Installation

## Requirements

| Package | Version | Why |
|---|---|---|
| PHP | 8.5 or later | Readonly classes, typed constants, `new` without parentheses. |

The library has no other requirement.

## Installing

```sh
composer require phil/poll
```

## A first count

The rules of the poll check each ballot as it is cast; the count reads the ballots back, from an array or from any
iterable a store streams:

```php
use Phil\Poll\CountingMethods;
use Phil\Poll\PollRules;

$rules = PollRules::multiple(['saturday', 'sunday', 'monday'], min: 1, max: 2);

$ballot = $rules->ballot($request->toArray()['choices'] ?? []);   // InvalidBallot when it breaks the rules
$store->keep($ballot->choices);

$methods = CountingMethods::defaults();
$result = $methods->count(CountingMethods::defaultFor($rules->kind), $rules, $store->ballots());
```

`CountingMethods::defaults()` holds the four methods of the library. It keeps no state between two counts: one
instance serves every request of a long-running worker.

In Symfony, `phil/poll-bundle` keeps the polls and their ballots with Doctrine, anonymous or not, and counts them on
demand.
