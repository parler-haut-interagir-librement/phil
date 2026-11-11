<!-- phil-dev source-sha256=f0758cc7a5fa4081960322e92f7acba4392d76036df209196a1222e4d7b90fbd -->
# Points d'extension

La bibliothèque a deux ports, tous deux des interfaces de `Phil\CircuitBreaker\Contract`. Leurs adaptateurs
appartiennent à l'application.

## `CircuitStateStore`

L'endroit où vit l'état de chaque circuit entre les requêtes et entre les processus : un pool de cache, un stockage
clé-valeur.

```php
interface CircuitStateStore
{
    public function load(CircuitKey $key): ?CircuitSnapshot;

    public function save(CircuitKey $key, CircuitSnapshot $snapshot): void;
}
```

- `load()` répond `null` pour un circuit jamais stocké, ou dont l'état a été perdu : le disjoncteur le lit comme un
  circuit fermé. C'est la direction sûre : les appels reprennent, et le circuit se déclenche de nouveau si le tiers
  échoue encore.
- `save()` garde l'instantané sous forme de valeurs simples — son état, `since` en horodatage, ses trois compteurs — et
  `CircuitSnapshot::restore()` le reconstruit.
- Rendre atomiques par un verrou une lecture et l'écriture qui la suit est une décision de l'adaptateur. Sans verrou,
  deux processus peuvent chacun compter un résultat sur le même instantané, et un compte est perdu ; le circuit s'ouvre
  tout de même, un appel ou deux plus tard.

Un stockage sur un client clé-valeur :

```php
use DateTimeImmutable;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\CircuitSnapshot;
use Phil\CircuitBreaker\CircuitState;
use Phil\CircuitBreaker\Contract\CircuitStateStore;

final readonly class KeyValueCircuitStore implements CircuitStateStore
{
    public function __construct(
        private KeyValueClient $client,
    ) {
    }

    public function load(CircuitKey $key): ?CircuitSnapshot
    {
        $value = $this->client->get('circuit.'.$key->value);
        $state = \is_array($value) ? CircuitState::tryFrom((string) ($value['state'] ?? '')) : null;

        if (null === $state) {
            return null;
        }

        return CircuitSnapshot::restore($state, new DateTimeImmutable('@'.(int) $value['since']), (int) $value['calls'], (int) $value['failures'], (int) $value['half_open_successes'], (int) ($value['slow_calls'] ?? 0));
    }

    public function save(CircuitKey $key, CircuitSnapshot $snapshot): void
    {
        $this->client->set('circuit.'.$key->value, [
            'state' => $snapshot->state->value,
            'since' => $snapshot->since->getTimestamp(),
            'calls' => $snapshot->calls,
            'failures' => $snapshot->failures,
            'half_open_successes' => $snapshot->halfOpenSuccesses,
            'slow_calls' => $snapshot->slowCalls,
        ]);
    }
}
```

`phil/circuit-breaker-bundle` fournit un stockage sur un pool de cache PSR-6.

## `CircuitOverride`

Si un opérateur a forcé l'ouverture d'un circuit, quoi que disent ses compteurs :

```php
interface CircuitOverride
{
    public function isForcedOpen(CircuitKey $key): bool;
}
```

L'adaptateur habituel lit un interrupteur que contrôle un opérateur — un indicateur de fonctionnalité par clé, de sorte
que forcer l'ouverture d'un circuit passe par une bascule auditée. Un circuit forcé reste confiné à sa propre clé : il
n'arrête jamais un sous-système entier. Le disjoncteur interroge le forçage à chaque appel : un adaptateur qui lit une
source distante garde donc sa réponse pour la durée de la requête.

`CircuitOverrides` interroge plusieurs forçages dans leur ordre : un circuit est forcé ouvert dès que l'un d'eux le
force, et les suivants ne sont pas interrogés. Sans aucun forçage, aucun circuit n'est jamais forcé ouvert.

```php
$overrides = new CircuitOverrides([$flagOverride, $maintenanceWindow]);
```
