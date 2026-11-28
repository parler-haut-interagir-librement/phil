<!-- phil-dev source-sha256=49d7b617b9eb9f743ad485c90f814fcbba49c03d3b35269e8c1c4cc42b464f77 -->
# Installation

```sh
composer require phil/bulk-actions
```

Le paquet exige PHP 8.5 ou une version plus récente et `ext-sodium`, pour les jetons et les identifiants d'exécution
aléatoires.

## La place de chaque service

| Service | Appelé par | Quand |
|---|---|---|
| `BulkSelector` | le contrôleur de la page de liste, sur un POST | la personne coche des lignes, ou choisit « tous les éléments de ce filtre » |
| `BulkPreviewer` | le contrôleur de la page de confirmation, sur un GET | la personne voit l'effet avant que quoi que ce soit change |
| `BulkConfirmer` | le contrôleur de la confirmation, sur un POST | la personne confirme |
| `ExecuteBulkRunHandler` et `BulkExecutor` | un *worker* de Messenger | chaque lot d'une exécution |
| `BulkRetrier` | le contrôleur de la page du rapport, sur un POST | la personne relance les éléments en échec |
| `BulkRun::report()` | la page du rapport | la personne lit ce qu'est devenu chaque élément |

Les services partagent un seul `Targets`, un seul `Authorizer` et un seul `Limits`, ainsi que les magasins que
l'application implémente ; voyez [Configuration](configuration.md) et [Points d'extension](extension-points.md).

## Le câblage dans une application Symfony

La bibliothèque n'a aucun bundle : les services sont déclarés une fois, avec les adaptateurs de l'application.

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

Chaque adaptateur fait quelques lignes ; [Points d'extension](extension-points.md) en donne un de chaque.
