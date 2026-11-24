# Installation

```sh
composer require phil/follow-bundle
```

Then register the bundle in `config/bundles.php`, after `PhilNotificationsBundle`:

```php
Phil\NotificationsBundle\PhilNotificationsBundle::class => ['all' => true],
Phil\FollowBundle\PhilFollowBundle::class => ['all' => true],
```

The package needs PHP 8.5 or later, Symfony 8.2 or later, Doctrine DBAL 4.5 or later and phil/notifications-bundle
0.3 or later.

## The tables

The bundle keeps its records in seven tables of the connection `connection` names, through DBAL. With the Doctrine
ORM, its schema listener adds them to the schema the ORM generates for that connection: `doctrine:migrations:diff`
writes the migration that creates them.

| Table | What it holds |
|---|---|
| `phil_follows` | One follow per member and subject, its level, its preferences and when it began |
| `phil_follow_requests` | The requests waiting for their approver, one per requester, subject and kind |
| `phil_follow_mutuals` | The mutual relationships, one row per pair of members |
| `phil_follow_activities` | The activities published, until their retention ends |
| `phil_follow_deliveries` | The claims of deliveries, one per activity and member |
| `phil_follow_unseen` | The notifications sent on every channel, by member and subject, until the member sees it |
| `phil_follow_visibility` | Whether each member who chose hides their followers |

The instants are `datetime_utc_immutable` columns. On MySQL and MariaDB, the identifiers use the `utf8mb4_bin`
collation, so that they compare byte by byte.

## The routes

The forms of the templates post to four routes; import them under a prefix of your choice:

```yaml
# config/routes/phil_follow.yaml
phil_follow:
    resource: '@PhilFollowBundle/config/routes.php'
    prefix: /follow
```

The forms check Symfony's CSRF token `phil_follow`, which the bundle declares stateless: `framework.csrf_protection`
stays on.

## Messenger and Scheduler

Without Messenger, an activity is sent to its followers when it is published. With Messenger, route the bundle's
messages to an asynchronous transport, beside the notifications':

```yaml
framework:
    messenger:
        routing:
            Phil\FollowBundle\Application\Message\FanOutActivity: async
            Phil\NotificationsBundle\Application\Message\SendNotification: async
```

With Symfony Scheduler, the schedule `phil_follow` purges every day; a worker consumes it:

```sh
bin/console messenger:consume scheduler_phil_follow
```
