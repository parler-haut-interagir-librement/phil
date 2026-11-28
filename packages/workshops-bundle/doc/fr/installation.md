<!-- phil-dev source-sha256=159d5aa331b5441836629b00eaaeb7be844b657621dd003c706b541a404fddf8 -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que le bundle déclare et sur lequel il est testé |
| Symfony | 8.2 ou plus récent | L'injection de dépendances et les contrats de traduction |
| Doctrine ORM | 3.8 ou plus récent | Les ateliers, les séances et les inscriptions sont des entités, triées par le `SortDirection` de PHP |
| Doctrine DBAL | 4.6 ou plus récent | Les moments dans son type `datetime_utc_immutable` |
| `phil/reservation` | 0.1 | La plage d'une séance, son `Interval` |
| `symfony/polyfill-php86` | 1.43 ou plus récent | Le `SortDirection` de PHP sur PHP 8.5 |
| `phil/waitlist-bundle` | 0.1, facultatif | Une liste d'attente par séance qui offre tour à tour les places libérées |

```sh
composer require phil/workshops-bundle
```

Avec Symfony Flex, la recette enregistre le bundle ; ses valeurs par défaut n'exigent aucun fichier de configuration.
Sans Flex, enregistrez le bundle dans `config/bundles.php` :

```php
// config/bundles.php
return [
    // …
    Phil\WorkshopsBundle\PhilWorkshopsBundle::class => ['all' => true],
];
```

Le bundle associe ses entités dans DoctrineBundle de lui-même. Créez les tables `phil_workshops_workshop`,
`phil_workshops_session` et `phil_workshops_registration` avec les migrations de l'application :

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Pour les listes d'attente, installez et enregistrez aussi phil/waitlist-bundle ; ses tables, `phil_waitlist` et
`phil_waitlist_entry`, viennent avec les mêmes migrations :

```sh
composer require phil/waitlist-bundle
```

Les services lisent l'heure du service `clock`, que FrameworkBundle enregistre avec `symfony/clock`, et envoient leurs
événements par le service `event_dispatcher` quand il existe.
