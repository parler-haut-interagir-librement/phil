<!-- phil-dev source-sha256=cc54777614ce2af7c25b510e261778f4593778af34e7c9b4ad1fd4c70839666c -->
# Installation

```sh
composer require phil/follow-bundle
```

Enregistrez ensuite le bundle dans `config/bundles.php`, après `PhilNotificationsBundle` :

```php
Phil\NotificationsBundle\PhilNotificationsBundle::class => ['all' => true],
Phil\FollowBundle\PhilFollowBundle::class => ['all' => true],
```

Le paquet exige PHP 8.5 ou une version plus récente, Symfony 8.2 ou une version plus récente, Doctrine DBAL 4.5 ou une
version plus récente et phil/notifications-bundle 0.3 ou une version plus récente.

## Les tables

Le bundle garde ses enregistrements dans sept tables de la connexion que nomme `connection`, par DBAL. Avec l'ORM de
Doctrine, son écouteur de schéma les ajoute au schéma que l'ORM génère pour cette connexion : `doctrine:migrations:diff`
écrit la migration qui les crée.

| Table | Ce qu'elle contient |
|---|---|
| `phil_follows` | Un suivi par membre et par sujet, son niveau, ses préférences et son début |
| `phil_follow_requests` | Les demandes qui attendent leur approbateur, une par demandeur, sujet et sorte |
| `phil_follow_mutuals` | Les relations mutuelles, une ligne par paire de membres |
| `phil_follow_activities` | Les activités publiées, jusqu'à la fin de leur conservation |
| `phil_follow_deliveries` | Les revendications de livraison, une par activité et par membre |
| `phil_follow_unseen` | Les notifications envoyées sur tous les canaux, par membre et par sujet, jusqu'à ce que le membre les voie |
| `phil_follow_visibility` | Si chaque membre qui a choisi cache ses abonnés |

Les instants sont des colonnes `datetime_utc_immutable`. Sur MySQL et MariaDB, les identifiants utilisent la collation
`utf8mb4_bin`, pour qu'ils se comparent octet par octet.

## Les routes

Les formulaires des gabarits envoient leurs données à quatre routes ; importez-les sous un préfixe de votre choix :

```yaml
# config/routes/phil_follow.yaml
phil_follow:
    resource: '@PhilFollowBundle/config/routes.php'
    prefix: /follow
```

Les formulaires vérifient le jeton CSRF `phil_follow` de Symfony, que le bundle déclare sans état :
`framework.csrf_protection` reste activé.

## Messenger et Scheduler

Sans Messenger, une activité est envoyée à ses abonnés dès sa publication. Avec Messenger, acheminez les messages du
bundle vers un transport asynchrone, à côté de ceux des notifications :

```yaml
framework:
    messenger:
        routing:
            Phil\FollowBundle\Application\Message\FanOutActivity: async
            Phil\NotificationsBundle\Application\Message\SendNotification: async
```

Avec Symfony Scheduler, le calendrier `phil_follow` purge chaque jour ; un worker le consomme :

```sh
bin/console messenger:consume scheduler_phil_follow
```
