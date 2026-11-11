<!-- phil-dev source-sha256=63572df2d2eecf20d6afd71b54b6d346353c798965f0dbd5ce85b8e34ba74795 -->
# Points d'extension

Le bundle n'a aucun port que l'application doit implémenter avant qu'il fonctionne. Ce que l'application écrit contre
lui, ce sont des sous-classes, des implémentations d'interfaces marqueurs et, si elle le veut, ses propres adaptateurs
des deux ports de bus.

| Point d'extension | Nature | Qui l'implémente | Identifiant de service ou étiquette |
|---|---|---|---|
| `Identifier` | classe abstraite | une sous-classe `final readonly` vide par agrégat | aucun |
| `IdentifierType` | type DBAL abstrait | une sous-classe par classe d'identifiant | un nom de type DBAL choisi par l'application |
| `DomainEvent` | interface | chaque événement d'un contexte | aucun |
| `DomainError` | interface | chaque exception du domaine d'un contexte | aucun |
| `Command`, `Query` | interfaces marqueurs | chaque message | aucun |
| `CommandHandler`, `QueryHandler` | marqueurs d'autoconfiguration | chaque *handler* | étiquette `messenger.message_handler` avec le bus configuré |
| `CommandBus`, `QueryBus` | ports | `MessengerCommandBus` et `MessengerQueryBus` par défaut ; une application peut les décorer ou les remplacer | alias `Phil\DddBundle\Application\Bus\CommandBus` et `…\QueryBus` |

## `Identifier`

- Contrat : rien à écrire. Déclarez `final readonly class XId extends Identifier {}`.
- Invariants : le constructeur est `final private` ; une sous-classe ne peut donc ni ajouter d'état ni contourner la
  validation.
- Voir [identifiers.md](identifiers.md).

## `IdentifierType`

- Contrat : `protected function identifierClass(): string`, qui retourne la `class-string` d'exactement une
  sous-classe d'`Identifier`. Retournez une constante : la méthode est appelée à chaque conversion.
- Invariants : un type par classe d'identifiant ; n'enregistrez jamais deux noms de type pour la même sous-classe dans
  un même schéma, sauf si les deux colonnes contiennent vraiment cet identifiant.
- Voir [doctrine-identifier-type.md](doctrine-identifier-type.md).

## `DomainEvent`

- Contrat : `occurredAt(): DateTimeImmutable`, l'heure à laquelle le fait s'est produit, prise d'une horloge injectée.
- Invariants : immuable ; porte des scalaires et des identifiants ; jamais un événement d'intégration.
- Voir [domain-events.md](domain-events.md).

## `DomainError`

- Contrat : rien au-delà de `Throwable`. Étendez l'exception SPL qui convient (`DomainException`,
  `RuntimeException`…) et implémentez l'interface.
- Invariants : levez-la seulement pour des refus sur lesquels l'appelant peut agir ; les pannes d'infrastructure ne
  sont pas des erreurs du domaine, sinon le bus de commandes les désenvelopperait comme si elles en étaient.
- Voir [errors.md](errors.md).

## `Command`, `Query`

- Contrat : aucun. Une `Query` déclare le type de son résultat avec `@implements Query<ResultType>`.
- Invariants : de simples DTO aux propriétés scalaires et tableaux, sûrs pour le sérialiseur JSON.

## `CommandHandler`, `QueryHandler`

- Contrat : une seule méthode publique `__invoke(TheMessage $message)` ; `void` pour un *handler* de commande, le
  résultat déclaré pour un *handler* de requête.
- Invariants : un seul marqueur par classe, jamais les deux ; aucun `#[AsMessageHandler]` en plus du marqueur ;
  exactement un *handler* par requête.
- Les services de l'application doivent être autoconfigurés.

```php
final readonly class CancelOrderHandler implements CommandHandler
{
    public function __construct(private OrderRepository $orders, private ClockInterface $clock)
    {
    }

    public function __invoke(CancelOrder $command): void
    {
        $order = $this->orders->get(OrderId::fromString($command->orderId));
        $order->cancel($this->clock->now()); // may throw a DomainError
    }
}
```

## `CommandBus`, `QueryBus`

- `CommandBus::dispatch(Command $command): void`. Contrat : remettre la commande à son *handler* ; laisser sortir une
  `DomainError` telle quelle ; ne rien retourner.
- `QueryBus::ask(Query $query): mixed`. Contrat : retourner le résultat de l'unique *handler* de la requête ; laisser
  sortir son échec tel quel.
- Une application peut décorer les alias avec la décoration de services de Symfony, pour journaliser ou tracer :

```php
use Phil\DddBundle\Application\Bus\Command;
use Phil\DddBundle\Application\Bus\CommandBus;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

#[AsDecorator(CommandBus::class)]
final readonly class LoggingCommandBus implements CommandBus
{
    public function __construct(
        #[AutowireDecorated] private CommandBus $inner,
        private LoggerInterface $logger,
    ) {
    }

    public function dispatch(Command $command): void
    {
        $this->logger->info('Dispatching {command}', ['command' => $command::class]);
        $this->inner->dispatch($command);
    }
}
```

- Dans un test unitaire, une implémentation en mémoire de l'un ou l'autre port tient en quelques lignes ; voir
  [testing.md](testing.md).
