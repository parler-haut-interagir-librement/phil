# Installation

```sh
composer require phil/rules
```

The package needs PHP 8.5 or later with the extension `mbstring`, and nothing else. It ships no Symfony bundle: an
application builds the registry of condition types it accepts and the readers it needs, usually as services.

## A first rule

A rule is built in PHP or read from its JSON form, against a registry of condition types. `ConditionTypes::builtIn()`
holds the generic comparisons on named facts:

```php
use Phil\Rules\Condition\All;
use Phil\Rules\Condition\Leaf;
use Phil\Rules\Condition\Not;
use Phil\Rules\Fact\Facts;
use Phil\Rules\Rule;
use Phil\Rules\Type\ConditionTypes;

$rule = new Rule(new All(
    new Leaf('fact.equals', ['fact' => 'channel', 'value' => 'web']),
    new Not(new Leaf('fact.contains', ['fact' => 'groups', 'value' => 'staff'])),
), ConditionTypes::builtIn());

$rule->evaluate(Facts::of(['channel' => 'web', 'groups' => ['members']]))->passed; // true
```

Building a `Rule` checks it: every type is registered, every operand accepted, the tree within its limits. A rule that
exists can be evaluated as many times as needed; it holds no state between evaluations.

## As services

```yaml
# config/services.yaml
services:
    Phil\Rules\Type\ConditionTypes:
        factory: ['Phil\Rules\Type\ConditionTypes', 'builtIn']

    Phil\Rules\Limits:
        arguments:
            $maxDepth: 8

    Phil\Rules\Json\RuleReader: ~
    Phil\Rules\Json\RuleWriter: ~
```

A package that declares condition types adds them with `ConditionTypes::with()`, from a factory of the application.
