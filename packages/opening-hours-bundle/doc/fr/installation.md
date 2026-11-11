<!-- phil-dev source-sha256=9103fc4dd25c3ef6bda667b548aeb647995d279376483e24e6882c1818c8b837 -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `ext-intl` | toutes | Les jours, les dates et les heures écrits par ICU |
| `ext-mbstring` | toutes | Les noms de jours avec une majuscule dans toute écriture |
| `phil/opening-hours` | ^0.1@dev | Les heures |
| `psr/clock` | ^1.0 | Maintenant, pour Twig ; le service `clock` du conteneur, que FrameworkBundle enregistre dès que `symfony/clock` est installé |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | Le formulaire |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | La classe du bundle et sa configuration |
| `doctrine/dbal` | facultatif | Le type de colonne `phil_opening_hours` |
| `twig/twig` | facultatif | Les fonctions et le gabarit de tableau |
| `symfony/clock` | obligatoire avec Twig | Le service `clock` où les fonctions lisent l'heure actuelle |

```sh
composer require phil/opening-hours-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\OpeningHoursBundle\PhilOpeningHoursBundle::class => ['all' => true],
];
```

## Configuration

```yaml
phil_opening_hours:
    # The time zone new hours are kept in, an IANA identifier; UTC by default.
    default_timezone: America/Toronto
```

Les fonctions Twig lisent l'heure actuelle dans le service `clock` — celui de `symfony/clock`, avec FrameworkBundle.
Quand Twig est installé, ce service est obligatoire : le bundle ne se rabat jamais sur l'heure du système, et un
conteneur sans lui ne se compile pas, en nommant le service `clock` manquant.
