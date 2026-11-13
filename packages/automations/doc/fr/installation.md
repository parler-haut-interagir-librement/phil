<!-- phil-dev source-sha256=fe374068029b7a913b7976f755280d84271802f81fb206204eda1a6d9010c026 -->
# Installation

```sh
composer require phil/automations
```

La bibliothèque exige PHP 8.5 ou une version plus récente et les composants de Symfony 8.2 dont elle dépend :
Messenger, Scheduler, ExpressionLanguage, Cache et le client HTTP. Les magasins de base de données exigent
`doctrine/dbal` 4.5 ou une version plus récente :

```sh
composer require doctrine/dbal
```

## Déclarer les services dans Symfony

La bibliothèque ne déclare aucun service. Dans une application Symfony, enregistrez les déclencheurs et les actions des
paquets avec les deux étiquettes que nomment les attributs, et construisez le catalogue à partir d'elles :

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

Acheminez les deux messages vers un transport asynchrone, et lancez un *worker* sur celui-ci et sur la planification :

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

Passez ensuite au moteur, depuis un écouteur, chaque événement qu'un déclencheur lit :

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

`Trigger::events()` liste les classes que lit chaque déclencheur, pour un écouteur qui les écoute toutes.

Les tables viennent de `configureSchema()` des magasins : voir [Stockage](storage.md).
