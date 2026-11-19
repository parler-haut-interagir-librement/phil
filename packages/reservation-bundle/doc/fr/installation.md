<!-- phil-dev source-sha256=7fe48f9117b2815834c053df998fbfcade0c95a23fcd362e61eb5880ac4f33dc -->
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

Avec Symfony Flex, la recette enregistre le bundle ; ses valeurs par défaut n'exigent aucun fichier de configuration.
Sans Flex, enregistrez le bundle dans `config/bundles.php` :

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
