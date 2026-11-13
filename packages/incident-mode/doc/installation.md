# Installation

```sh
composer require phil/incident-mode
```

The library needs PHP 8.5 or later with `ext-sodium`, `psr/clock` and `psr/event-dispatcher`. The adapters need their
own package, installed by the application that uses them:

| Adapter | Package |
|---|---|
| `Dbal\DbalActivationStore`, `Dbal\IncidentTables` | `doctrine/dbal` |
| `Dbal\IncidentSchemaListener` | `doctrine/orm` |
| `Messenger\MessengerLiftScheduler` and its handlers | `symfony/messenger` |

## In a Symfony application

With `phil/moderation-bundle` installed, the bundle declares every service below; there is nothing to write. Without
it, declare them:

```yaml
# config/services.yaml
services:
    Phil\IncidentMode\Presets:
        factory: ['Phil\IncidentMode\Presets', 'defaults']

    Phil\IncidentMode\Dbal\DbalActivationStore:
        arguments: ['@doctrine.dbal.default_connection']
    Phil\IncidentMode\Contract\ActivationStore: '@Phil\IncidentMode\Dbal\DbalActivationStore'

    Phil\IncidentMode\Dbal\IncidentSchemaListener:
        arguments: ['@doctrine.dbal.default_connection']
        tags: [{ name: doctrine.event_listener, event: postGenerateSchema }]

    Phil\IncidentMode\Messenger\MessengerLiftScheduler:
        arguments: ['@messenger.default_bus', '@clock']
    Phil\IncidentMode\Contract\LiftScheduler: '@Phil\IncidentMode\Messenger\MessengerLiftScheduler'

    Phil\IncidentMode\IncidentMode:
        arguments:
            $clock: '@clock'
            $events: '@event_dispatcher'

    Phil\IncidentMode\Messenger\LiftIncidentHandler:
        autoconfigure: true
    Phil\IncidentMode\Messenger\LiftExpiredIncidentsHandler:
        autoconfigure: true
```

Then generate the migration that creates the table, route `LiftIncident` to a transport that supports delays, and add
the recurring sweep to a schedule, as [Automatic lifting](automatic-lifting.md) shows:

```sh
php bin/console doctrine:migrations:diff
```
