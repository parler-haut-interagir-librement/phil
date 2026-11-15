<!-- phil-dev source-sha256=808a9271bdd60a7ca9a0263bdceb96cf1b57b7d57cc46f21821345dda998e66d -->
# Ventes différées

Une vente différée est une commande passée payée plus tard : un dépôt d'abord, puis un solde appelé par un
déclencheur. Les précommandes, les versements et les mises de côté sont trois réglages d'une même politique, pas trois
mécanismes. **Une commande a au plus une vente différée**, donc une seule échéance différée.

| Type | Rôle |
|---|---|
| `Domain\ValueObject\DeferredSalePolicy` | Dépôt, déclencheur du solde, engagement du stock, livraison, jours jusqu'à l'échéance ; `preorder()`, `installments()`, `layaway()` |
| `Domain\ValueObject\Deposit` | Ce qui est demandé d'abord : `none()`, `share()` en points de base, `fixed()` |
| `Domain\Deferred\BalanceTrigger` | `Date`, `Shipment`, `Availability`, `Manual` |
| `Domain\Deferred\StockCommitment` | `OnOrder`, `OnBalance` |
| `Domain\Deferred\Fulfilment` | `OnDeposit`, `OnBalance` |
| `Domain\Deferred\DeferredSaleState` | `AwaitingDeposit`, `AwaitingBalance`, `BalanceDue`, `Settled`, `Cancelled` |
| `Domain\Model\DeferredSale` | La vente d'une commande, par son numéro, dans `phil_commerce_deferred_sale` |
| `Domain\Event\DeferredSaleEvent` et ses sept sous-classes | Ce qui est arrivé à une vente, par le numéro de sa commande |
| `Domain\Contract\DeferredSaleRepository` | Trouve et stocke les ventes, liste les soldes échus ; `DoctrineDeferredSaleRepository` avec DoctrineBundle |
| `Domain\Exception\DeferredSaleRefused` | Une vente qui ne peut être ouverte, appelée ou annulée |
| `Application\Contract\StockCommitments` | Le port qui retient et libère le stock ([extension-points.md](extension-points.md#stockcommitments)) |
| `Application\DeferredSales` | Ouvre, suit, appelle, annule ; ce qui est dû, si la marchandise peut partir |

## La politique

| Réglage | Valeurs | Sens |
|---|---|---|
| Dépôt | `Deposit::none()`, `share(2_500)`, `fixed($money)` | Rien, une part du total en points de base, ou un montant fixe plafonné au total |
| Déclencheur | `Date`, `Shipment`, `Availability`, `Manual` | Ce qui appelle le solde une fois le dépôt payé |
| Engagement du stock | `OnOrder`, `OnBalance` | Le stock est retenu à l'ouverture de la vente, ou une fois le solde payé |
| Livraison | `OnDeposit`, `OnBalance` | La marchandise peut partir une fois le dépôt payé, ou une fois le solde payé |
| Jours jusqu'à l'échéance | 1 à 3 650, `Date` seulement | L'échéance comptée depuis l'ouverture, à moins qu'une date soit donnée alors |

| Constructeur nommé | Déclencheur | Stock | La marchandise part |
|---|---|---|---|
| `preorder($deposit)` | `Availability` par défaut ; `Shipment`, `Date` ou `Manual` | `OnBalance` par défaut, ou `OnOrder` | Au solde |
| `installments($deposit, $days)` | `Date` | `OnOrder` | Au dépôt |
| `layaway($deposit, $days)` | `Date` | `OnOrder` | Au solde |

Toute autre combinaison se construit avec le constructeur. Une part est arrondie comme `Money::allocate()` partage le
total ; un dépôt est gardé tel que calculé à l'ouverture de la vente.

## États

```text
awaiting deposit → awaiting balance → balance due → settled
awaiting deposit, awaiting balance → settled (paid in full early)
awaiting deposit, awaiting balance, balance due → cancelled
```

- **La vente suit les paiements de la commande**, que `DeferredSales::recordPayments()` lit par `Payments` : le dépôt
  est reçu dès que ce qui a été capté, moins ce qui est reparti, l'atteint, et la vente est réglée dès que cela atteint
  le total de la commande. Un dépôt nul est reçu à l'ouverture de la vente.
- Le solde n'est appelé qu'une fois le dépôt payé, et seulement par le déclencheur de la vente ; une date ne l'appelle
  qu'une fois l'échéance atteinte.
- Payer plus que demandé n'est jamais refusé : la vente se règle plus tôt.
- Une vente réglée ou annulée ne bouge plus.

## Exemple

```php
use Phil\CommerceBundle\Application\DeferredSales;
use Phil\CommerceBundle\Domain\Deferred\BalanceTrigger;
use Phil\CommerceBundle\Domain\ValueObject\DeferredSalePolicy;
use Phil\CommerceBundle\Domain\ValueObject\Deposit;

// A layaway: a fifth now, the rest within 60 days; the goods are put aside at once.
$sale = $deferredSales->open($order, DeferredSalePolicy::layaway(Deposit::share(2_000), 60), $clock->now());
$payments->start($order, 'card', $clock->now(), $deferredSales->amountDue($order));

// After each payment notification or refund:
$deferredSales->recordPayments($order, $clock->now());

// A scheduled task calls the balances whose due date is reached, 100 at a time:
$deferredSales->callDue($clock->now());

// A preorder: the logistics module or the catalogue says the goods arrived.
$deferredSales->callBalance($order->number, BalanceTrigger::Availability, $clock->now());

$deferredSales->mayFulfil($order->number); // whether the goods may leave
```

`callBalance()` retourne `false`, sans rien changer, pour une commande sans vente différée qui attend ce déclencheur :
qui voit un déclencheur l'appelle pour chaque commande qu'il touche. `callDue()` prend de 1 à 1 000 ventes à la fois,
la plus tôt échue d'abord ; une tâche planifiée l'appelle de nouveau jusqu'à ce qu'il en retourne moins que sa limite.

## Événements et stock

Chaque changement donne ses événements, dans l'ordre où ils sont arrivés :

| Événement | Quand |
|---|---|
| `DeferredSaleOpened` | La vente s'ouvre |
| `DepositReceived` | Les paiements atteignent le dépôt |
| `BalanceCalled` | Le déclencheur est venu |
| `DeferredSaleSettled` | Les paiements atteignent le total |
| `DeferredSaleCancelled` | La vente est abandonnée |
| `StockCommitted` | Le stock est retenu : à l'ouverture avec `OnOrder`, une fois réglée avec `OnBalance` |
| `StockReleased` | Une vente annulée libère le stock qu'elle retenait |

`DeferredSales` appelle d'abord chaque service `StockCommitments` pour `StockCommitted` et `StockReleased`, puis
distribue chaque événement par le distributeur d'événements de Symfony, selon sa classe. Un événement porte le numéro
de la commande et le moment ; un écouteur lit ce qu'il lui faut de plus dans son propre stockage. Un module écoute
sans que le noyau le connaisse :

```php
use Phil\CommerceBundle\Domain\Event\BalanceCalled;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class AskForTheBalance
{
    public function __invoke(BalanceCalled $event): void
    {
        // send the buyer the link to pay what is due
    }
}
```

Les événements sont distribués avant que l'unité de travail soit écrite, ce que fait l'application, comme pour les
commandes et les paiements ; un écouteur qui envoie quelque chose au monde extérieur le fait après la validation, par
Messenger par exemple.

## Annuler

`DeferredSales::cancel()` abandonne la vente et libère le stock qu'elle retenait. **Ce qui a été payé, et la commande
elle-même, reviennent à l'application** : elle rembourse par `Payments::refund()`, en gardant ce que permettent les
conditions de la boutique, et annule la commande. Une mise de côté dont l'échéance est passée sans paiement revient à
l'application à annuler ; le bundle n'annule jamais de lui-même.

## Stockage

Une rangée par vente dans `phil_commerce_deferred_sale`, par `order_number`, avec une colonne de version ; le dépôt en
unités mineures avec sa devise ; `balance_trigger`, `stock_commitment`, `fulfilment` et `state` comme les valeurs de
leurs énumérations ; `stock_committed` ; `opened_at`, `due_at`, `deposit_paid_at`, `balance_called_at`, `closed_at` ;
un index sur `state` et `due_at`, que `dueForBalance()` lit par DBAL, les numéros de commande seulement.
