<!-- phil-dev source-sha256=d14b91d3454e591db8b09bff270cf5921b0ad1e422c4fa588f7c07f339c37e5b -->
# Les changements d'une commande passée

Une commande passée change par un changement qui est préparé, puis confirmé ou abandonné, et chaque changement confirmé
compte une révision de plus de la commande. La différence qu'il fait au total est connue avant et après.

```php
use Phil\CommerceBundle\Application\OrderChanges;
use Phil\CommerceBundle\Domain\ValueObject\OrderChangeLine;

// $changes: OrderChanges, from the container
$change = $changes->prepare($order, [
    OrderChangeLine::of(SellableReference::of('product', 'dice'), 3), // three dice instead of one
    OrderChangeLine::of(SellableReference::of('product', 'mat'), 0),  // no mat any more
], 'Customer called', 'employee-7', $context, $now);

$change->difference(); // what the preview adds to the total, negative when it lowers it

$changes->confirm($change, $order, 'employee-7', $context, $now);
$order->revision();                                  // 2
$payments->summary($order)->payable();               // what the order now lacks
$payments->summary($order)->overpaid();              // what it holds beyond its total, to refund
```

- **Préparer ne touche à rien** : l'aperçu est calculé sur une copie de la commande, rouverte et changée par
  `OrderModifier` aux prix du moment ; le changement garde le total d'avant et le total que donne l'aperçu.
- **Confirmer applique le changement** : la commande est rouverte, ses lignes fixées, recalculée et passée de nouveau —
  par le workflow quand il est câblé ([lifecycle.md](lifecycle.md)) —, puis `Order::recordRevision()` compte sa nouvelle
  révision, que le changement garde avec le total auquel la commande a abouti.
- **Un changement est confirmé sur la révision où il a été préparé** : une fois un autre changement confirmé, un plus
  ancien est refusé comme périmé (`OrderChangeRefused::stale()`), et préparé de nouveau.
- **Seule une commande passée sans reçu change** : une fois son reçu émis, une note de crédit et une nouvelle commande
  la remplacent ([receipts.md](receipts.md)).
- **Une ligne fixe la quantité à laquelle la commande aboutit** : 0 la retire, une référence absente de la commande
  l'ajoute, sans options ; une ligne avec des options se change dans le panier, par `OrderAmendments`.
- **Les paiements restent** : ce qui manque à la commande se paie comme tout solde, ce qu'elle tient au-delà de son total
  se rembourse ([refunds.md](refunds.md)).

Les états d'un `OrderChange` sont `prepared`, `confirmed` et `abandoned` ; les changements sont stockés dans
`phil_commerce_order_change` par `OrderChangeRepository` (`DoctrineOrderChangeRepository` avec DoctrineBundle).
