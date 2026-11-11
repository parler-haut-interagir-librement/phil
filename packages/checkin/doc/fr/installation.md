<!-- phil-dev source-sha256=bfff861bcf1146139d77a6f7ab81764e677081a361ccfaffb9bb1a53bee87e99 -->
# Installation

## Prérequis

| Paquet | Version | Raison |
|---|---|---|
| PHP | 8.5 ou plus récent | Les classes en lecture seule, `array_any()`, `new` sans parenthèses. |
| `ext-sodium` | toute | Les clés de manifeste en base64 adapté aux URL et les identifiants de lecture en hexadécimal, en temps constant. |
| `psr/clock` | `^1.0` | Les lectures sont horodatées, les titres ouvrent et ferment, les manifestes expirent, sur une horloge PSR-20. |

## Installer

```sh
composer require phil/checkin
```

## Construire le guichet

Le guichet a besoin d'un résolveur de codes, d'un registre et d'une horloge ; la politique est facultative. Le résolveur
et le registre appartiennent à l'application ([Points d'extension](extension-points.md)).

```php
use Phil\Checkin\AdmissionPolicy;
use Phil\Checkin\CheckInDesk;
use Phil\Checkin\CredentialResolvers;

$desk = new CheckInDesk(
    new CredentialResolvers([$tickets, $registrations]),
    $ledger,
    $clock,
    new AdmissionPolicy(earlySeconds: 1800, antiPassback: true),
);
```

Pour les appareils hors ligne, l'émetteur de manifestes et la synchronisation ont aussi besoin du catalogue de
l'application :

```php
use Phil\Checkin\Offline\ManifestIssuer;
use Phil\Checkin\Offline\OfflineSync;

$issuer = new ManifestIssuer($catalog, $ledger, $clock, $policy, seconds: 43_200, limit: 10_000, labels: false);
$sync = new OfflineSync($catalog, $ledger, $clock, $policy, skewSeconds: 300, maxAgeSeconds: 172_800);
```

Aucun d'eux ne garde d'état entre les appels : chaque lecture lit et écrit le registre. On peut les partager sans risque
entre les requêtes d'un *worker* de longue durée.

Dans Symfony, `phil/checkin-bundle` les construit à partir de la configuration, avec un registre tenu par Doctrine.
