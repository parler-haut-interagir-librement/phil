<!-- phil-dev source-sha256=d437e26be325aa11e280329bd61fdc9c36e920953444b5a204ac529995d7d50a -->
# Installation

```sh
composer require phil/incident-mode
```

La bibliothèque exige PHP 8.5 ou une version plus récente avec `ext-sodium`, `psr/clock` et `psr/event-dispatcher`. Les
adaptateurs ont besoin de leur propre paquet, installé par l'application qui les utilise :

| Adaptateur | Paquet |
|---|---|
| `Dbal\DbalActivationStore`, `Dbal\IncidentTables` | `doctrine/dbal` |
| `Dbal\IncidentSchemaListener` | `doctrine/orm` |
| `Messenger\MessengerLiftScheduler` et ses handlers | `symfony/messenger` |

## Dans une application Symfony

Avec `phil/moderation-bundle` installé, le bundle déclare chacun des services ci-dessous ; il n'y a rien à écrire. Sans
lui, déclarez-les :

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

Générez ensuite la migration qui crée la table, acheminez `LiftIncident` vers un transport qui prend en charge les
délais, et ajoutez le balayage récurrent à un calendrier, comme le montre [Levée automatique](automatic-lifting.md) :

```sh
php bin/console doctrine:migrations:diff
```
