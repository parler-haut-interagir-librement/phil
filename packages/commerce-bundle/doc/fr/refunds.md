<!-- phil-dev source-sha256=31374e2b9c07d0ea8c3d68b43a200366fd365268552cfc4d80141c6683f4e625 -->
# Les remboursements en deux temps

Un remboursement est accordé, puis exécuté, chacun par une personne que l'application autorise : deux droits, pour que
la personne qui décide ne soit pas la seule à déplacer de l'argent. Une petite boutique où une seule personne détient
les deux les fusionne.

```php
use Phil\CommerceBundle\Application\Refunds;
use Phil\CommerceBundle\Domain\Model\Refund;

// $refunds: Refunds, from the container
// $entityManager: Doctrine\ORM\EntityManagerInterface
$refund = $entityManager->wrapInTransaction(
    fn (): Refund => $refunds->grant($payment, $amount, 'Broken die', 'clerk-3', $now),
);
$refunds->execute($refund, 'manager-2', $now);   // asks the processor, or records an offline refund
$refunds->withdraw($refund, $now);               // or gives it up before it is executed
```

- **Ce qui est accordé ne dépasse jamais ce qui reste** : le montant du paiement, moins les remboursements accordés et
  pas encore exécutés, et moins le plus grand de ce que le processeur a confirmé et de ce qui lui a été demandé ; le
  paiement est complété. Un remboursement exécuté mais pas encore confirmé reste donc dû, et les remboursements d'un
  paiement ne dépassent jamais son montant.
- **Deux remboursements accordés en même temps s'attendent** : `grant()` verrouille d'abord le paiement, par le port
  `PaymentLock` (voyez [Deux commis à la fois](#deux-commis-à-la-fois)).
- **Exécuter passe par `Payments::refund()`** : le processeur est sollicité, avec l'identifiant du remboursement comme
  clé d'idempotence, de sorte qu'un remboursement demandé de nouveau — après un délai dépassé ou un plantage — ne soit
  fait qu'une fois ; le remboursement est enregistré quand sa notification arrive ; le remboursement d'un paiement hors
  ligne est enregistré aussitôt
  ([payments.md](payments.md#paiements-hors-ligne)).
- **Qui a accordé et qui a exécuté sont gardés**, avec leurs moments, sur le `Refund`, dont les états sont `granted`,
  `executed` et `withdrawn`.
- **`phil_commerce.refunds.single_step: true`** fait exécuter aussitôt en accordant, par la même personne.
- **Les deux droits sont ceux de l'application** : elle les vérifie avec ses voters de sécurité avant d'appeler
  `grant()` et `execute()`.

## Deux commis à la fois

Deux commis qui accordent un remboursement du même paiement au même moment compteraient chacun ce qui reste avant que
le remboursement de l'autre soit stocké, et pourraient ensemble accorder plus que le paiement. `grant()` prend donc le
verrou du paiement avant de compter : avec DoctrineBundle, `DoctrinePaymentLock` lit la rangée du paiement avec
`SELECT … FOR UPDATE`, sur MySQL, MariaDB et PostgreSQL pareillement, et la tient jusqu'à la fin de la transaction. Le
second commis attend que la transaction du premier soit validée ou annulée, puis compte le remboursement du premier.

- **`grant()` s'exécute dans une transaction**, que l'application valide avec le remboursement, comme ci-dessus : hors
  d'une transaction, le verrou finirait avec son instruction, et `grant()` lance une `LogicException` avant de rien
  compter. Avec `single_step`, le processeur est sollicité dans cette transaction, pendant que le paiement est
  verrouillé.
- **Les remboursements sont lus sous le verrou** par une lecture verrouillante, qui voit ce que l'autre transaction a
  validé, même sous les lectures répétables de MySQL, où un `SELECT` simple garde l'instantané de la première lecture
  de la transaction.
- **Un paiement modifié depuis sa lecture** — la notification d'un processeur appliquée entre-temps par une autre
  requête — lance l'`OptimisticLockException` de Doctrine, à partir de la version du paiement : l'application relit le
  paiement et accorde sur la copie à jour.
- **Le paiement et ses remboursements sont ceux que le stockage tient** : ce qui reste n'est jamais compté à partir
  d'une copie en mémoire plus ancienne que le verrou.

### Mise à niveau depuis 0.3

`grant()` appelé hors d'une transaction lance une `LogicException` à partir de la 0.4 : enveloppez chaque appel dans
`EntityManagerInterface::wrapInTransaction()`, ou entre `beginTransaction()` et `commit()`, avec le vidage du
remboursement. Un appelant qui attrape `PaymentRefused` pour montrer au commis ce qui reste attrape aussi
`OptimisticLockException`, et relit le paiement.

Une application qui stocke les paiements ailleurs fait un alias vers son propre `PaymentLock`
([extension-points.md](extension-points.md#paymentlock)) ; sans lui, `Refunds` compte les remboursements que donne
`RefundRepository::refundsOf()`, sans verrou, ce qui convient à un seul processus, comme dans les tests.

## Le remboursement annoncé

Une fois un remboursement exécuté, `Refunds` envoie `OrderRefunded`, par sa classe : le numéro de la commande, les
identifiants du remboursement et du paiement, le canal de la commande et l'identifiant stable de son client, le
montant remboursé et le moment. Un fil d'activité ou un programme de fidélité retire ce que la commande a rapporté sans
que le commerce le sache ([extension-points.md](extension-points.md#événements)). Un remboursement accordé ou retiré
n'est pas annoncé ; une commande que `Refunds` ne peut pas lire laisse le canal et le client à `null`. Sans
répartiteur d'événements, rien n'est annoncé.

## Stockage

Les remboursements sont stockés dans `phil_commerce_refund` par `RefundRepository` (`DoctrineRefundRepository` avec
DoctrineBundle). `Payments::refund()` reste disponible pour un flux qui n'a besoin d'aucun octroi.
