# Installation

```sh
composer require phil/automations
```

The library needs PHP 8.5 or later and the Symfony 8.2 components it requires: Messenger, Scheduler,
ExpressionLanguage, Cache and the HTTP client. The database stores need `doctrine/dbal` 4.5 or later:

```sh
composer require doctrine/dbal
```

## Declaring the services in Symfony

The library declares no service. In a Symfony application, register the triggers and actions of the packages with the
two tags the attributes name, and build the catalogue from them:

```yaml
# config/services.yaml
services:
    _instanceof:
        Phil\Automations\Contract\Trigger:
            tags: ['phil.automations.trigger']
        Phil\Automations\Contract\Action:
            tags: ['phil.automations.action']

    Phil\Automations\Expression\ExpressionConditions: ~

    Phil\Automations\Catalogue\Catalogue:
        arguments:
            $triggers: !tagged_iterator phil.automations.trigger
            $actions: !tagged_iterator phil.automations.action
            $evaluators: ['@Phil\Automations\Expression\ExpressionConditions']

    Phil\Automations\Dbal\DbalDefinitions: ~
    Phil\Automations\Dbal\DbalRunStore: ~
    Phil\Automations\Contract\Definitions: '@Phil\Automations\Dbal\DbalDefinitions'
    Phil\Automations\Contract\RunStore: '@Phil\Automations\Dbal\DbalRunStore'

    Phil\Automations\Engine\AutomationEngine: ~
    Phil\Automations\Messenger\AdvanceRunHandler: ~
    Phil\Automations\Messenger\ResumeDueRunsHandler: ~
    Phil\Automations\Messenger\AutomationsSchedule: ~
```

Route the two messages to an asynchronous transport, and run a worker on it and on the schedule:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            automations: '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
            Phil\Automations\Messenger\AdvanceRun: automations
            Phil\Automations\Messenger\ResumeDueRuns: automations
```

```sh
php bin/console messenger:consume automations scheduler_automations
```

Then pass each event a trigger reads to the engine, from a listener:

```php
use Phil\Automations\Engine\AutomationEngine;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class StartAutomations
{
    public function __construct(private AutomationEngine $engine)
    {
    }

    public function __invoke(OrderPaid $event): void
    {
        $this->engine->handle($event);
    }
}
```

`Trigger::events()` lists the classes each trigger reads, for a listener that subscribes to all of them.

The tables come from the stores' `configureSchema()`: see [Storage](storage.md).
