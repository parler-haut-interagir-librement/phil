<!-- phil-dev source-sha256=2215306f342534750304bfcca04717d2d3d3fd253840b4fcb1a13b6485b591f7 -->
# Installation

## Prérequis

| Paquet | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Classes en lecture seule, constantes typées, `new` sans parenthèses, `Random\Randomizer`. |
| `ext-mbstring` | toute | Chaque texte d'un événement est coupé à un nombre de caractères, en UTF-8 valide. |
| `ext-sodium` | toute | Les identifiants d'événements sont écrits en hexadécimal sans fuite par le temps d'exécution. |
| `psr/clock` | `^1.0` | Les événements sont datés par une horloge PSR-20. |
| `phil/ip-anonymizer` | `^0.1` | L'adresse du client n'est gardée que telle que la laisse l'une de ses stratégies. |

## Installer

```sh
composer require phil/error-tracking
```

## Construire le traqueur

`ErrorTracker` a besoin d'une `EventFactory` et d'un magasin ; le reste a une valeur par défaut.

```php
use Phil\ErrorTracking\ErrorTracker;
use Phil\ErrorTracking\EventFactory;
use Phil\ErrorTracking\Fingerprinter;
use Phil\ErrorTracking\Sampler;
use Phil\ErrorTracking\Scrubber;
use Symfony\Component\Clock\NativeClock;

$tracker = new ErrorTracker(
    new EventFactory('/srv/app', new NativeClock(), release: '1.4.0', environment: 'prod'),
    $store,                                  // the application's ErrorStore
    new Scrubber(['birth_date']),            // fields of the application, beside the defaults
    new Fingerprinter(),
    new Sampler(1.0),                        // keep every event
    $throttle,                               // a CaptureThrottle, or null
    $alerter,                                // a GroupAlerter, or null
);
```

Le répertoire du projet donné à la fabrique décide des cadres qui appartiennent à l'application : un fichier sous ce
répertoire et hors de son répertoire `vendor/`. Ses deux derniers arguments bornent les cadres d'une trace (50) et les
exceptions d'une chaîne (10).

Le traqueur ne garde rien entre deux captures : une seule instance sert toutes les requêtes d'un *worker* de longue
durée.
