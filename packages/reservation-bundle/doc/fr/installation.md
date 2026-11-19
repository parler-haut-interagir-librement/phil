<!-- phil-dev source-sha256=6015b7e874083cac3e7b89329db462fde78f273c9615b5efd42534b587c0b84e -->
# Installation

| Prérequis | Version | Raison |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que le bundle déclare et sur lequel il est testé |
| Symfony | 8.2 ou plus récent | Les contrats d'injection de dépendances, de formulaires et de traduction |
| Doctrine ORM | 3.7 ou plus récent | Les ressources et les réservations sont des entités, triées par le `SortDirection` de PHP |
| Doctrine DBAL | 4.5 ou plus récent | Les moments dans son type `datetime_utc_immutable` |
| `symfony/polyfill-php86` | 1.37 ou plus récent | Le `SortDirection` de PHP sur PHP 8.5 |
| `symfony/console` | facultatif | Les deux commandes |
| `symfony/messenger` | facultatif | Les rappels envoyés sous forme de messages |
| `phil/calendar-bundle` | facultatif | Les réservations confirmées dans les vues du calendrier |

```sh
composer require phil/reservation-bundle
```

Enregistrez le bundle quand Symfony Flex ne le fait pas :

```php
// config/bundles.php
return [
    // …
    Phil\ReservationBundle\PhilReservationBundle::class => ['all' => true],
];
```

Le bundle déclare lui-même le mappage de ses entités dans DoctrineBundle. Créez les tables
`phil_reservation_resource` et `phil_reservation` avec les migrations de l'application :

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Les services lisent l'heure dans le service `clock`, qu'enregistre FrameworkBundle avec `symfony/clock`.
