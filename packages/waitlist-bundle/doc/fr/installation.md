<!-- phil-dev source-sha256=004d9757d0ba71041a861ad48d30912bbaf1711ffed51e5b6608f89324276d3d -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récente | Le plancher que le bundle déclare et sur lequel il est testé |
| Symfony | 8.2 ou plus récente | L'injection de dépendances, la console et les identifiants |
| Doctrine ORM | 3.8 ou plus récente | Les listes et leurs entrées sont des entités, mappées par attributs |
| `phil/notifications-bundle` | 0.4, facultatif | Pour informer les participants de leurs offres ; voyez [Notifications](notifications.md) |

```sh
composer require phil/waitlist-bundle
```

Avec Symfony Flex, la recette enregistre le bundle ; ses valeurs par défaut n'exigent aucun fichier de configuration.
Sans Flex, enregistrez le bundle dans `config/bundles.php` :

```php
// config/bundles.php
return [
    // …
    Phil\WaitlistBundle\PhilWaitlistBundle::class => ['all' => true],
];
```

Le bundle mappe seul ses entités dans DoctrineBundle. Créez les tables `phil_waitlist` et `phil_waitlist_entry` avec les
migrations de l'application :

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Les services lisent l'heure du service `clock`, que FrameworkBundle enregistre avec `symfony/clock`, et envoient leurs
événements par le service `event_dispatcher` quand il y en a un.

## Faire expirer les offres selon un horaire

Une offre expire quand `phil:waitlist:lapse` s'exécute après son échéance. Exécutez-la chaque minute, depuis Symfony
Scheduler :

```php
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;

$schedule->add(RecurringMessage::every('1 minute', new RunCommandMessage('phil:waitlist:lapse')));
```

ou depuis cron :

```text
* * * * * php /path/to/bin/console phil:waitlist:lapse --quiet
```

Chaque exécution fait expirer au plus `batch` offres sur chacune d'au plus `batch` listes ; l'exécution suivante prend
le reste.
