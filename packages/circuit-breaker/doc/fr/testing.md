<!-- phil-dev source-sha256=31afe615c56c258ab91ee4ad5bf49048d7e6c5a3a63dade7eea15b090ba5be19 -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` teste les clés et leurs bornes ; les politiques, leurs bornes et le seuil exact en arithmétique
entière ; chaque transition de la machine à états à la seconde — la fenêtre qui recommence, la durée d'ouverture, les
succès d'essai, l'échec qui rouvre un circuit mi-ouvert, le résultat en course avec un circuit ouvert — ; le disjoncteur
sur un stockage en mémoire et une horloge figée, un état partagé par deux disjoncteurs d'une même clé, un circuit forcé
qui n'inscrit rien ; le registre ; et les forçages interrogés ensemble.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/circuit-breaker/tests
```

La bibliothèque est tenue par Infection à un score de mutation couverte de 100 %.

## Le banc d'essai

`tests/Benchmark/CircuitBreakerBench.php` mesure ce que coûte chaque appel protégé : l'interrogation, l'interrogation et
le rapport d'un succès, une transition d'un instantané, et l'analyse d'une clé. `composer bench`, depuis la racine du
monodépôt, l'exécute avec les autres bancs d'essai.

## Tester du code derrière un disjoncteur

Construisez le disjoncteur sur un stockage en mémoire et une horloge figée, comme `MockClock` de Symfony ; déclenchez-le
en inscrivant des échecs, et avancez l'horloge pour le laisser se rétablir :

```php
use Phil\CircuitBreaker\CircuitBreaker;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\CircuitOverrides;
use Phil\CircuitBreaker\CircuitPolicy;
use Symfony\Component\Clock\MockClock;

$clock = new MockClock('2026-03-14 10:30:00');
$breaker = new CircuitBreaker(CircuitKey::of('http.geocoding'), CircuitPolicy::of(50, 2, 60, 30), $memory, new CircuitOverrides(), $clock);

$breaker->recordFailure();
$breaker->recordFailure();
self::assertNull($geocoder->locate('1 Main St'));   // the fallback, and no call made

$clock->sleep(30);
```

Un stockage en mémoire tient en une douzaine de lignes : un tableau d'instantanés par valeur de clé, `load()` qui le lit
et `save()` qui l'écrit.
