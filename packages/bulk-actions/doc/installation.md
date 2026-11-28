# Installation

```sh
composer require phil/bulk-actions
```

The package needs PHP 8.5 or later and `ext-sodium`, for the random tokens and run identifiers.

## Where each service fits

| Service | Called by | When |
|---|---|---|
| `BulkSelector` | the list page's controller, on a POST | the person ticks rows, or chooses "every item of this filter" |
| `BulkPreviewer` | the confirmation page's controller, on a GET | the person sees the effect before anything changes |
| `BulkConfirmer` | the confirmation's controller, on a POST | the person confirms |
| `ExecuteBulkRunHandler` and `BulkExecutor` | a Messenger worker | each chunk of a run |
| `BulkRetrier` | the report page's controller, on a POST | the person runs the failed items again |
| `BulkRun::report()` | the report page | the person reads what became of each item |

The services share one `Targets`, one `Authorizer` and one `Limits`, and the stores the application implements; see
[Configuration](configuration.md) and [Extension points](extension-points.md).

## Wiring in a Symfony application

The library has no bundle: the services are declared once, with the application's adapters.

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true

    Phil\BulkActions\Target\Limits: ~
    Phil\BulkActions\Target\Targets:
        arguments: [!tagged_iterator app.bulk_target]
    Phil\BulkActions\BulkSelector: ~
    Phil\BulkActions\BulkPreviewer: ~
    Phil\BulkActions\BulkConfirmer: ~
    Phil\BulkActions\BulkExecutor: ~
    Phil\BulkActions\BulkRetrier: ~
    Phil\BulkActions\Bridge\ExternalSelection: ~
    Phil\BulkActions\Messenger\ExecuteBulkRunHandler:
        tags: [messenger.message_handler]

    Phil\BulkActions\Contract\Authorizer: '@App\Bulk\SecurityAuthorizer'
    Phil\BulkActions\Contract\SelectionStore: '@App\Bulk\DbalSelectionStore'
    Phil\BulkActions\Contract\RunStore: '@App\Bulk\DbalRunStore'
    Phil\BulkActions\Contract\Transactions: '@App\Bulk\DoctrineTransactions'
    Phil\BulkActions\Contract\RunDispatcher: '@App\Bulk\MessengerRunDispatcher'
    Phil\BulkActions\Contract\AuditTrail: '@App\Bulk\AuditTrailAdapter'
```

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            Phil\BulkActions\Messenger\ExecuteBulkRun: async
```

```yaml
# config/packages/framework.yaml
framework:
    csrf_protection:
        stateless_token_ids: [bulk_actions]
```

Each adapter is a few lines; [Extension points](extension-points.md) gives one of each.
