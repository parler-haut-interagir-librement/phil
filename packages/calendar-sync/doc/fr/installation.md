<!-- phil-dev source-sha256=8f9b856e247283ac3f7244f4077b806b357d72feadd0a976d813de14d0d5e67c -->
# Installation

```sh
composer require phil/calendar-sync
```

Le paquet exige PHP 8.5 ou une version plus récente avec `ext-dom`, `ext-intl`, `ext-libxml` et `ext-mbstring`, le
HttpClient, la HTTP foundation et le Clock de Symfony 8.2, ainsi que `phil/recurrence`, `phil/safe-http` et `phil/url`.

## Câbler les services

Le paquet est une bibliothèque : une application déclare ses services. Dans une application Symfony, avec le câblage
automatique :

```yaml
services:
    Phil\CalendarSync\Adapter\IcsFeed: ~
    Phil\CalendarSync\Adapter\CalDav: ~
    Phil\CalendarSync\Adapter\GoogleCalendar: ~
    Phil\CalendarSync\Adapter\MicrosoftGraph: ~

    Phil\CalendarSync\CalendarSync:
        arguments:
            $connections: '@App\Calendar\Connections'       # implements Contract\ConnectionRepository
            $credentials: '@App\Calendar\Credentials'       # implements Contract\CredentialStore
            $store: '@App\Calendar\BusyTimes'               # implements Contract\BusyStore
            $sources:
                - '@Phil\CalendarSync\Adapter\IcsFeed'
                - '@Phil\CalendarSync\Adapter\CalDav'
                - '@Phil\CalendarSync\Adapter\GoogleCalendar'
                - '@Phil\CalendarSync\Adapter\MicrosoftGraph'
            $destinations:
                - '@Phil\CalendarSync\Adapter\CalDav'
                - '@Phil\CalendarSync\Adapter\GoogleCalendar'
                - '@Phil\CalendarSync\Adapter\MicrosoftGraph'
```

Les adaptateurs prennent un client HTTP — celui de Symfony par défaut — et ne gardent rien entre deux appels : ils sont
sûrs dans un *worker* qui sert plusieurs requêtes.

## Messages

Avec Symfony Messenger, enregistrez les deux *handlers* et acheminez les deux messages vers un transport asynchrone :

```yaml
services:
    Phil\CalendarSync\Message\RefreshBusyTimesHandler:
        tags: [messenger.message_handler]
    Phil\CalendarSync\Message\SyncEventHandler:
        arguments:
            $events: '@App\Calendar\OutboundEvents'         # implements Contract\OutboundEvents
        tags: [messenger.message_handler]

framework:
    messenger:
        routing:
            Phil\CalendarSync\Message\RefreshBusyTimes: async
            Phil\CalendarSync\Message\SyncEvent: async
```
