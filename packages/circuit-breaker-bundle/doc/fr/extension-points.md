<!-- phil-dev source-sha256=b507568f5727c18ea74285dba2a3a85e3247a2e286d389bbba11adbd0191008e -->
# Points d'extension

## Le stockage des états

`state_store` nomme un service qui implémente `Phil\CircuitBreaker\Contract\CircuitStateStore` et qui remplace
`CacheCircuitStateStore` : un stockage clé-valeur, une table de base de données. Le contrat — `load()` qui répond `null`
pour un état jamais stocké ou perdu, le choix d'un verrou — est décrit par `phil/circuit-breaker`.

```yaml
phil_circuit_breaker:
    state_store: App\Circuits\RedisCircuitStore
```

## Les forçages

Un circuit est forcé ouvert dès qu'un service étiqueté `phil_circuit_breaker.override`
(`PhilCircuitBreakerBundle::OVERRIDE_TAG`) le force, et jamais quand aucun n'est étiqueté. Un service de l'application
qui implémente `Phil\CircuitBreaker\Contract\CircuitOverride` est étiqueté par l'autoconfiguration :

```php
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\Contract\CircuitOverride;

final readonly class MaintenanceWindows implements CircuitOverride
{
    public function __construct(
        private MaintenanceCalendar $calendar,
    ) {
    }

    public function isForcedOpen(CircuitKey $key): bool
    {
        return $this->calendar->isUnderMaintenance($key->value);
    }
}
```

Un service enregistré sans autoconfiguration porte lui-même l'étiquette. Les forçages sont interrogés à chaque appel à
un disjoncteur, et le premier qui force le circuit met fin au tour : un forçage qui lit une source distante garde sa
réponse pour la durée de la requête, et la réinitialise entre les requêtes avec `kernel.reset`.

`phil/feature-flags-bundle` en étiquette un : un circuit est forcé ouvert tant que son indicateur, `circuit-open.<key>`,
est actif.

## Les clés des circuits

Le paramètre `phil_circuit_breaker.circuit_keys` (`PhilCircuitBreakerBundle::CIRCUIT_KEYS`) contient les clés des
circuits déclarés, une liste de chaînes, vide sans circuit. Un bundle qui dérive quelque chose par circuit — un
indicateur, une métrique, une alerte — le lit dans une passe de compilation, une fois que chaque bundle a chargé sa
configuration ; les clés sont déjà validées.
