<!-- phil-dev source-sha256=8aad81198b2125e3f730efa710d7f228b3cc8c20e8f77cf2fc1eec65bdf1c81a -->
# Installation

## Prérequis

| Paquet | Version | Raison |
|---|---|---|
| PHP | 8.5 ou plus récent | Les classes en lecture seule, les constantes typées, `new` sans parenthèses. |
| `psr/clock` | `^1.0` | Les fenêtres et les durées d'ouverture sont mesurées sur une horloge PSR-20. |

## Installer

```sh
composer require phil/circuit-breaker
```

## Construire les disjoncteurs

Un disjoncteur a besoin de sa clé, de sa politique, d'un stockage des états, d'un forçage et d'une horloge. Le stockage
et le forçage appartiennent à l'application ([Points d'extension](extension-points.md)) ; `new CircuitOverrides()` est
le forçage d'une application où aucun opérateur ne force de circuit.

```php
use Phil\CircuitBreaker\CircuitBreaker;
use Phil\CircuitBreaker\CircuitBreakers;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\CircuitOverrides;
use Phil\CircuitBreaker\CircuitPolicy;

$overrides = new CircuitOverrides([$operatorSwitch]);

$breakers = new CircuitBreakers([
    new CircuitBreaker(CircuitKey::of('http.payments'), CircuitPolicy::of(50, 10, 60, 30), $store, $overrides, $clock),
    new CircuitBreaker(CircuitKey::of('webhook.partner-a'), CircuitPolicy::of(80, 5, 300, 120), $store, $overrides, $clock),
]);
```

Les disjoncteurs ne gardent aucun état propre entre les appels : chaque interrogation lit le stockage, chaque rapport
l'écrit. On peut les partager sans risque entre les requêtes d'un *worker* de longue durée.

Dans Symfony, `phil/circuit-breaker-bundle` les construit à partir de la configuration, avec un stockage sur un pool de
cache PSR-6.
