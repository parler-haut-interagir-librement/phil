# Installation

```sh
composer require phil/calendar-sync
```

The package needs PHP 8.5 or later with `ext-dom`, `ext-intl`, `ext-libxml` and `ext-mbstring`, Symfony 8.2's
HttpClient, HTTP foundation and Clock, and `phil/recurrence`, `phil/safe-http` and `phil/url`.

## Wiring the services

The package is a library: an application declares its services. In a Symfony application, with autowiring:

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

The adapters take an HTTP client — Symfony's by default — and keep nothing between calls: they are safe in a worker
that serves many requests.

## Messages

With Symfony Messenger, register the two handlers and route the two messages to an asynchronous transport:

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
