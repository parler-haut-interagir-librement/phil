<!-- phil-dev source-sha256=d7b26fc42c1eabfd58bb0a487e6f259f48d800dc973c6d76d00dfe5a86ec0d60 -->
# Installation

```sh
composer require phil/rules
```

Le paquet exige PHP 8.5 ou une version plus récente avec l'extension `mbstring`, et rien d'autre. Il ne fournit aucun
bundle Symfony : l'application construit le registre des types de condition qu'elle accepte et les lecteurs dont elle a
besoin, généralement comme services.

## Une première règle

Une règle se construit en PHP ou se lit depuis sa forme JSON, contre un registre de types de condition.
`ConditionTypes::builtIn()` contient les comparaisons génériques sur des faits nommés :

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

Construire une `Rule` la vérifie : chaque type est enregistré, chaque opérande accepté, l'arbre dans ses limites. Une
règle qui existe peut être évaluée autant de fois que nécessaire ; elle ne conserve aucun état entre deux évaluations.

## Comme services

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

Un paquet qui déclare des types de condition les ajoute avec `ConditionTypes::with()`, depuis une fabrique de
l'application.
