<!-- phil-dev source-sha256=1642bdec566b754a4be2c0979bbfd707bfc186870caa72a6da2735c2779bd42b -->
# phil/circuit-breaker

**Un disjoncteur par tiers : les appels cessent aussitôt quand il échoue sans arrêt, et reprennent quand il se
rétablit.**

phil/circuit-breaker protège les appels qu'une application fait à un tiers — une API HTTP, un point de terminaison de
webhook, un relais de courriel — avec un circuit par clé. Tant que le circuit est fermé, les appels passent et leurs
résultats sont comptés dans une fenêtre fixe. Quand au moins un nombre minimal d'appels a été fait et qu'assez d'entre
eux ont échoué, ou ont duré plus que la politique ne le permet, le circuit s'ouvre et les appels vont directement à
leur solution de repli, sans attendre un délai d'expiration. Après sa durée d'ouverture, le circuit laisse passer des
appels d'essai (mi-ouvert) : assez de succès le ferment, un seul échec le rouvre. Un tiers en échec ne déclenche jamais
que son propre circuit.

Il s'adresse à toute application PHP qui dépend de services qu'elle ne contrôle pas : une boutique qui débite des cartes
par un fournisseur de paiement, un site qui envoie des webhooks à des partenaires, un outil qui géocode des adresses. Un
opérateur peut forcer l'ouverture d'un circuit, quoi que disent ses compteurs, par un forçage propre à l'application —
un indicateur de fonctionnalité, par exemple.

C'est une bibliothèque PHP simple, écrite de zéro, dont le seul prérequis est `psr/clock`. La machine à états est pure :
chaque transition prend une politique et un instant et retourne un nouvel instantané ; elle est donc testée à la seconde
et stockée sous forme de valeurs simples. L'endroit où vivent les états entre les requêtes et entre les processus est un
port, `CircuitStateStore`, tout comme le forçage de l'opérateur, `CircuitOverride`.

La conception est petite : une `CircuitKey` nomme ce qui est protégé, une `CircuitPolicy` dit quand il s'ouvre et
comment il se rétablit, un `CircuitSnapshot` est l'état à un instant, un `CircuitBreaker` les réunit avec le stockage,
le forçage et l'horloge, et `CircuitBreakers` contient les disjoncteurs d'une application par clé.

## État

| Partie | État |
|---|---|
| Clés, politiques (taux d'échec sur un minimum d'appels, fenêtre fixe, durée d'ouverture, succès en mi-ouvert) | Implémenté et testé |
| Appels lents : un résultat qui porte sa durée, un seuil de lenteur et un taux d'appels lents dans la politique | Implémenté et testé |
| La machine à états fermé/ouvert/mi-ouvert en transitions pures | Implémenté et testé |
| `CircuitBreaker` et `CircuitBreakers` sur un stockage, un forçage et une horloge PSR-20 | Implémenté et testé |
| `CircuitOverride` et `CircuitOverrides`, les interrupteurs de l'opérateur interrogés ensemble | Implémenté et testé |
| Une fenêtre glissante, une limite d'appels d'essai simultanés à l'état mi-ouvert | Pas commencé : elles exigent un stockage partagé et un verrou, et relèvent d'un adaptateur de stockage |
| Un bundle Symfony : circuits issus de la configuration, stockage PSR-6, clients HTTP derrière leurs circuits, panneau du profileur | Implémenté dans `phil/circuit-breaker-bundle` |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, la construction des disjoncteurs. |
| [Circuits](doc/fr/circuits.md) | Les clés, les politiques, la machine à états et ses transitions. |
| [Utiliser un disjoncteur](doc/fr/using-a-breaker.md) | `CircuitBreaker`, `CircuitBreakers`, ce qui compte comme un échec, la solution de repli. |
| [Points d'extension](doc/fr/extension-points.md) | Le stockage des états et les forçages de l'application. |
| [Exceptions](doc/fr/exceptions.md) | `CircuitBreakerException` et ses deux exceptions. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, le seuil de mutation et le banc d'essai, et comment tester du code derrière un disjoncteur. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/circuit-breaker
```

```php
use Phil\CircuitBreaker\CircuitBreaker;
use Phil\CircuitBreaker\CircuitBreakers;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\CircuitOverrides;
use Phil\CircuitBreaker\CircuitPolicy;
use Symfony\Component\Clock\NativeClock;

$payments = CircuitKey::of('http.payments');
$breakers = new CircuitBreakers([
    new CircuitBreaker($payments, CircuitPolicy::of(50, 10, 60, 30), $store, new CircuitOverrides(), new NativeClock()),
]);

$breaker = $breakers->get($payments);

if (!$breaker->isAvailable()) {
    return $fallback;                // open: no call, no timeout
}

try {
    $answer = $client->charge($order);
} catch (PaymentsUnreachable $unreachable) {
    $breaker->recordFailure();

    return $fallback;
}

$breaker->recordSuccess();
```

`$store` est le `CircuitStateStore` de l'application : voir [Points d'extension](doc/fr/extension-points.md).

## Prérequis

PHP 8.5 ou plus récent et `psr/clock`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
