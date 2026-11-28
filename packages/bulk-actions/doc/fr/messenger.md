<!-- phil-dev source-sha256=58f65e1e7bc7ee7a334a5e8558fb1388a3a0ac13aee1a0f3f061a5f8adb2e769 -->
# Exécution avec Messenger

## Les messages

La confirmation envoie l'exécution par `Contract\RunDispatcher`. Avec Symfony Messenger, l'adaptateur envoie un
`ExecuteBulkRun`, qui porte l'identifiant de l'exécution et rien d'autre : aucun élément, aucune modification, aucun
renseignement personnel n'attend dans une file.

```php
use Phil\BulkActions\Contract\RunDispatcher;
use Phil\BulkActions\Messenger\ExecuteBulkRun;
use Phil\BulkActions\Run\RunId;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerRunDispatcher implements RunDispatcher
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public function dispatch(RunId $run): void
    {
        $this->bus->dispatch(new ExecuteBulkRun($run->value));
    }
}
```

`ExecuteBulkRunHandler` exécute un lot et envoie de nouveau l'exécution tant qu'il lui reste du travail ; une
exécution de 10 000 éléments donne donc 100 messages courts plutôt qu'un long, et le reste du travail s'intercale.

## Un lot

`BulkExecutor::execute()` lit l'exécution et rend la main aussitôt quand elle est inconnue ou terminée : un message
livré deux fois ne change rien de plus. Ensuite :

1. Il redemande le droit de lancer l'action ; une personne qui l'a perdu arrête l'exécution, et les éléments restants
   sont refusés avec la raison `bulk_actions.reason.access_lost`. Une action retirée de la configuration arrête aussi
   l'exécution, ses éléments en échec avec `bulk_actions.reason.unknown_action`.
2. Pour un filtre, une fois traités les éléments lus avant, il demande au dépôt s'il accepte encore le filtre, puis lit
   les identifiants suivants après le curseur.
3. Il charge les `Limits::chunkSize` éléments en attente suivants et vérifie chacun : manquant ou d'une autre classe,
   refusé par le voter, ou à une autre version que celle que la personne a vue. Ceux-là sont enregistrés, pas appliqués.
4. Il applique les autres dans une transaction, en y enregistrant l'exécution, de sorte qu'avec un magasin d'exécutions
   dans la même base, les résultats sont validés avec les changements.
5. Quand la transaction échoue, elle est annulée, et chaque élément est relu et appliqué dans sa propre transaction :
   `ItemRefused` donne un élément refusé avec sa raison, `VersionConflict` un conflit, tout autre `Throwable` un élément
   en échec avec la raison `bulk_actions.reason.error`.
6. Il enregistre l'exécution, la termine quand plus rien n'est en attente et que le filtre n'a plus rien, et enregistre
   `AuditEvent::Finished`.

## Les effets hors de la base de données

Une action n'envoie jamais un courriel et n'appelle jamais un service depuis `apply()` : un lot annulé l'aurait envoyé
quand même. Elle consigne l'effet dans une boîte d'envoi, comme `phil/outbox`, dans la même transaction, sous
`ItemContext::idempotencyKey()`. La clé est la même pour chaque tentative sur l'élément ; un élément appliqué de nouveau
après une connexion perdue consigne donc son effet une seule fois.

```php
$this->outbox->record('member.segment_changed', ['member' => $context->item], idempotencyKey: $context->idempotencyKey());
```

## Les transactions

`Contract\Transactions::run()` valide le travail, ou l'annule et oublie ce que l'ORM retient en mémoire avant de lancer
de nouveau l'exception : les éléments appliqués un par un après l'échec d'un lot sont relus à neuf.

```php
use Closure;
use Doctrine\Persistence\ManagerRegistry;
use Phil\BulkActions\Contract\Transactions;
use Throwable;

final readonly class DoctrineTransactions implements Transactions
{
    public function __construct(private ManagerRegistry $doctrine)
    {
    }

    public function run(Closure $work): void
    {
        $manager = $this->doctrine->getManager();

        try {
            $manager->wrapInTransaction(static function () use ($work): void {
                $work();
            });
        } catch (Throwable $throwable) {
            $this->doctrine->resetManager();

            throw $throwable;
        }
    }
}
```

L'exception de verrou optimiste d'un ORM au `flush` fait échouer le lot ; l'élément appliqué seul échoue alors de
nouveau, et une action qui l'attrape et lance `VersionConflict` signale un conflit plutôt qu'un échec.

## Les rapports

```php
$report = $runStore->get(RunId::fromString($id))?->report();

$report->status;                         // RunStatus::Pending, Running, Finished or Stopped
$report->counts();                       // ['pending' => 0, 'succeeded' => 9_812, 'denied' => 3, ...]
$report->with(ItemOutcome::Failed);      // list<ItemResult>, each with its item and reason
$report->truncated;                      // whether a filter grew past Limits::maxItems
```

`ItemOutcome::translationKey()` donne `bulk_actions.outcome.succeeded` et ses semblables ; les raisons sont aussi des
clés de traduction, celles de la bibliothèque ou celles qu'une action a données à `ItemRefused`.

## Les nouvelles tentatives

```php
$retrier->retry($user, RunId::fromString($id));                 // the failed items
$retrier->retry($user, RunId::fromString($id), conflicts: true); // and the conflicts
```

Une nouvelle tentative exige une exécution terminée et au moins un élément en échec, ou en conflit sur demande ; sinon
elle lance `NothingToRetry`. Le droit est demandé pour la personne qui relance, qui exécute les éléments à partir de ce
moment. La tentative s'applique aux éléments tels qu'ils sont maintenant, sans les versions de la sélection. Les
éléments réussis, refusés par leur voter, refusés par l'action et manquants restent tels quels. `AuditEvent::Retried` est enregistré et
l'exécution est envoyée de nouveau.
