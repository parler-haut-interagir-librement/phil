<!-- phil-dev source-sha256=c29bd4b1efff15f09105c1e29f1cec142671b074835bded76156012578e0f72e -->
# Utiliser un disjoncteur

## Les appels à un tiers

Interrogez le disjoncteur avant l'appel, envoyez l'appel à sa solution de repli quand il répond `false`, et rapportez le
résultat :

```php
use Phil\CircuitBreaker\CallOutcome;
use Phil\CircuitBreaker\CircuitBreakers;
use Phil\CircuitBreaker\CircuitKey;

final readonly class Geocoder
{
    public function __construct(
        private CircuitBreakers $circuits,
        private GeocodingApi $api,
    ) {
    }

    public function locate(string $address): ?Position
    {
        $breaker = $this->circuits->get(CircuitKey::of('http.geocoding'));

        if (!$breaker->isAvailable()) {
            return null;                      // the fallback: a cached answer, a queue, a message
        }

        $started = hrtime(true);

        try {
            $position = $this->api->locate($address);
        } catch (GeocodingUnavailable) {
            $breaker->record(CallOutcome::failed((int) ((hrtime(true) - $started) / 1_000_000)));

            return null;
        }

        $breaker->record(CallOutcome::succeeded((int) ((hrtime(true) - $started) / 1_000_000)));

        return $position;
    }
}
```

Les résultats qui comptent comme des échecs sont la décision de l'appelant : un délai d'expiration ou une erreur serveur
est un échec du tiers, une réponse qui refuse une requête mal formée ne l'est généralement pas. Il en va de même de la
solution de repli de chaque appel, et de l'alerte que lève un circuit ouvert. La durée, mesurée sur une horloge
monotone comme `hrtime()`, permet à une politique avec un seuil de lenteur de compter les appels lents ;
`recordSuccess()` et `recordFailure()` rapportent un résultat qui n'a pas été mesuré.

## `CircuitBreaker`

| Membre | Effet |
|---|---|
| `key` | La `CircuitKey` publique. |
| `isAvailable(): bool` | `false` quand il est forcé ouvert ou ouvert. Applique d'abord les transitions dues au temps et ne les enregistre que si elles ont changé quelque chose. |
| `state(): CircuitState` | `Open` quand il est forcé ouvert ; sinon, l'état après les transitions dues au temps. |
| `record(CallOutcome $outcome): void` | Inscrit comment s'est passé un appel, sa durée comptée face au seuil de lenteur de la politique, et enregistre le nouvel instantané ; ne fait rien tant qu'il est forcé ouvert. |
| `recordSuccess(): void` | Inscrit un succès et enregistre le nouvel instantané ; ne fait rien tant qu'il est forcé ouvert. |
| `recordFailure(): void` | Inscrit un échec et enregistre le nouvel instantané ; ne fait rien tant qu'il est forcé ouvert. |

Un circuit forcé ouvert par son forçage est indisponible quoi que disent ses compteurs, et n'inscrit rien : ses
compteurs reprennent là où ils étaient quand le forçage est levé.

## `CircuitBreakers`

| Méthode | Retourne |
|---|---|
| `get(CircuitKey $key): CircuitBreaker` | Le disjoncteur ; une clé non déclarée lève `UnknownCircuit`. Un appel à un tiers n'obtient jamais par défaut un circuit non déclaré : sa politique est une décision, écrite avec l'appel. |
| `has(CircuitKey $key): bool` | Si la clé est déclarée. |
| `keys(): list<CircuitKey>` | Les clés déclarées, dans leur ordre. |

Une clé déclarée deux fois lève `InvalidCircuit` à la construction du registre.
