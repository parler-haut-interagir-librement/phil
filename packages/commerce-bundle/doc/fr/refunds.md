<!-- phil-dev source-sha256=b18aa7a2157b05c8a28133f09f03123c6e748d92ca9138f3f4f820f41498c342 -->
# Les remboursements en deux temps

Un remboursement est accordé, puis exécuté, chacun par une personne que l'application autorise : deux droits, pour que
la personne qui décide ne soit pas la seule à déplacer de l'argent. Une petite boutique où une seule personne détient
les deux les fusionne.

```php
use Phil\CommerceBundle\Application\Refunds;

// $refunds: Refunds, from the container
$refund = $refunds->grant($payment, $amount, 'Broken die', 'clerk-3', $now);
$refunds->execute($refund, 'manager-2', $now);   // asks the processor, or records an offline refund
$refunds->withdraw($refund, $now);               // or gives it up before it is executed
```

- **Ce qui est accordé ne dépasse jamais ce qui reste** : le montant du paiement, moins les remboursements accordés et
  pas encore exécutés, et moins le plus grand de ce que le processeur a confirmé et de ce qui lui a été demandé ; le
  paiement est complété. Un remboursement exécuté mais pas encore confirmé reste donc dû, et les remboursements d'un
  paiement ne dépassent jamais son montant.
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
