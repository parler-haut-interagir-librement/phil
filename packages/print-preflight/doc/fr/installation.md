<!-- phil-dev source-sha256=bd40337aaad24ab80bcb8d8e64cdfce5dd47e5627e77de292f73e255519fd3d8 -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Classes en lecture seule, constantes typées, `array_any()`, `new` sans parenthèses |
| `phil/fonts` | Les permissions d'intégration d'une police, lues dans sa table `OS/2` |

La bibliothèque n'a aucune autre dépendance : aucun binaire, aucun service, aucun lecteur PDF.

## Installer

```sh
composer require phil/print-preflight
```

C'est une bibliothèque, pas un bundle : il n'y a rien à enregistrer. Construisez un `Preflight` là où un document est
vérifié :

```php
use Phil\PrintPreflight\Preflight;
use Phil\PrintPreflight\PrintProfile;

$preflight = new Preflight(new PrintProfile(pageMultiple: 4));
$report = $preflight->check($document);
```

Un `Preflight` ne garde aucun état entre deux vérifications : une seule instance, construite à partir du profil de
l'imprimeur, peut servir chaque document envoyé à cet imprimeur. Une application qui travaille avec plusieurs
imprimeurs garde un profil par imprimeur.
