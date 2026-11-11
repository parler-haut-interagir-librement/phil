<!-- phil-dev source-sha256=649f8258b76bdcbb8fc1677342ed4462f823eb0c74f9a3e3e5d2e6ccb7c6287d -->
# Bus de commandes et de requêtes

Six types dans `Phil\DddBundle\Application\Bus`, et leurs deux adaptateurs Messenger dans
`Phil\DddBundle\Infrastructure\Messenger`.

| Type | Nature | Rôle |
|---|---|---|
| `Command` | interface marqueur | Une intention de changer l'état |
| `Query` | interface marqueur, générique sur son résultat (`@template TResult`) | Une question posée au modèle de lecture |
| `CommandBus` | port | `dispatch(Command $command): void` |
| `QueryBus` | port | `ask(Query $query): mixed`, typé `TResult` pour l'analyse statique |
| `CommandHandler` | marqueur d'autoconfiguration | Étiquette le *handler* sur le bus de commandes |
| `QueryHandler` | marqueur d'autoconfiguration | Étiquette le *handler* sur le bus de requêtes |
| `MessengerCommandBus` | adaptateur de `CommandBus` | Répartit sur le bus de commandes configuré |
| `MessengerQueryBus` | adaptateur de `QueryBus` | Répartit sur le bus de requêtes configuré et retourne le résultat du *handler* |

## Commandes

```php
namespace App\Ordering\Application\Command;

use Phil\DddBundle\Application\Bus\Command;

final readonly class PlaceOrder implements Command
{
    public function __construct(
        public string $orderId,
        public string $customerId,
        /** @var list<array{sku: string, quantity: int}> */
        public array $lines,
    ) {
    }
}
```

```php
namespace App\Ordering\Application\Command;

use Phil\DddBundle\Application\Bus\CommandHandler;

final readonly class PlaceOrderHandler implements CommandHandler
{
    public function __construct(private OrderRepository $orders)
    {
    }

    public function __invoke(PlaceOrder $command): void
    {
        $this->orders->add(Order::place(OrderId::fromString($command->orderId), /* … */));
    }
}
```

```php
$orderId = OrderId::generate();
$commandBus->dispatch(new PlaceOrder($orderId->toString(), $customerId, $lines));
// $orderId is already known: redirect to the order's page.
```

- **Une commande ne retourne rien.** L'appelant génère l'identifiant de ce qui est créé (`Identifier::generate()`) et
  le passe dans la commande.
- **Un *handler* de commande a une seule méthode `__invoke` et retourne `void`.** Messenger lit la classe du message
  dans le type de son paramètre.
- **Le bus de commandes est le bus transactionnel.** Ses *middlewares*, déclarés par l'application, enveloppent le
  *handler* dans une transaction (`doctrine_transaction` avec Doctrine).

## Requêtes

```php
use Phil\DddBundle\Application\Bus\Query;

/** @implements Query<OrderSummary|null> */
final readonly class FindOrderSummary implements Query
{
    public function __construct(public string $orderId)
    {
    }
}
```

```php
use Doctrine\DBAL\Connection;
use Phil\DddBundle\Application\Bus\QueryHandler;

final readonly class FindOrderSummaryHandler implements QueryHandler
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(FindOrderSummary $query): ?OrderSummary
    {
        $row = $this->connection->fetchAssociative('SELECT … FROM orders WHERE id = ?', [/* … */]);

        return false === $row ? null : OrderSummary::fromRow($row);
    }
}
```

```php
$summary = $queryBus->ask(new FindOrderSummary($orderId)); // OrderSummary|null for PHPStan and Psalm
```

- **Une requête contourne les agrégats.** Elle lit des DTO par DBAL ou en SQL. On charge un agrégat pour faire
  respecter un invariant, et une lecture n'en fait respecter aucun.
- **Un seul *handler* par requête.** `ask()` retourne le résultat d'exactement un *handler* ; aucun ou plusieurs
  *handlers* donnent une `LogicException` de Messenger.
- **Le bus de requêtes est synchrone et non transactionnel.** Ne routez jamais une requête vers un transport : `ask()`
  ne trouverait aucun résultat et échouerait.

## Pourquoi les marqueurs décident du bus

Avec deux bus et de simples *handlers* `#[AsMessageHandler]`, un *handler* qui ne nomme aucun bus est enregistré sur
tous les bus. Une commande peut alors être traitée par le bus de requêtes, hors de sa transaction, sans que rien ne le
signale. Le bundle ferme cette porte : chaque classe qui implémente `CommandHandler` est étiquetée
`messenger.message_handler` avec `bus: <command_bus>`, et chaque `QueryHandler` avec `bus: <query_bus>`. Le marqueur
est le seul endroit où le choix se fait, et un réviseur le voit dans la déclaration de la classe.

N'ajoutez pas `#[AsMessageHandler]` à une classe qui implémente déjà un marqueur : l'attribut enregistrerait une
seconde définition de *handler*, avec ses propres options.

## Comment sortent les échecs

| Bus | Échec dans le *handler* | Ce que reçoit l'appelant |
|---|---|---|
| Commandes | Une `DomainError` | La `DomainError` elle-même, désenveloppée |
| Commandes | Plusieurs *handlers* échouent, dont un avec une `DomainError` | La première `DomainError` parmi les exceptions enveloppées |
| Commandes | Toute autre chose | La `HandlerFailedException` de Messenger, inchangée |
| Requêtes | N'importe quoi | L'exception levée par le *handler*, désenveloppée, quel que soit son type |

L'asymétrie est voulue. Une commande peut avoir plusieurs *handlers*, et plusieurs choses peuvent échouer : seule
l'erreur du domaine a un sens pour l'appelant, et désenvelopper tout autre échec le ferait passer pour le refus d'un
invariant. Une requête a exactement un *handler* : son unique échec sort donc tel quel, et l'appelant attrape un
« introuvable » sans connaître Messenger.

## Messages sur un transport

Les commandes peuvent être routées vers un transport asynchrone. Deux conséquences :

- **Utilisez le sérialiseur JSON.** La sérialisation native de PHP (`unserialize()`), celle de Messenger par défaut,
  est une surface d'injection d'objets. Les messages sont donc de simples DTO qui portent des scalaires et des
  tableaux, jamais des entités Doctrine, des ressources ou quoi que ce soit dont la reconstruction dépend de l'état du
  conteneur. Dans les exemples, les identifiants voyagent comme chaînes et deviennent des objets `Identifier` dans le
  *handler*.
- **Une commande asynchrone échoue dans le *worker*, pas chez l'appelant.** `dispatch()` retourne dès l'envoi du
  message, et une `DomainError` levée plus tard est prise en charge par les transports de nouvelle tentative et
  d'échec de Messenger.

## Utiliser les adaptateurs sans le bundle

Les deux adaptateurs sont de simples classes sur un `Symfony\Component\Messenger\MessageBusInterface` :

```php
$commandBus = new MessengerCommandBus($messengerCommandBus);
$queryBus = new MessengerQueryBus($messengerQueryBus);
```
