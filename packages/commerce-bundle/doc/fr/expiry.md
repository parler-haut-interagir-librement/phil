<!-- phil-dev source-sha256=61ea5bae702fde0d4da1203cc2cb25da07b6d8bd01ecf3537f2e2e453062c40c -->
# L'expiration des commandes impayées

Une commande passée que personne ne paie expire, et une commande expirée est supprimée une fois gardée assez
longtemps, si bien qu'une boutique ne garde que le nécessaire des commandes qui ne sont jamais devenues des ventes.

| Délai | Valeur par défaut | Où il se fixe |
|---|---|---|
| Heures avant qu'une commande passée impayée expire | 24 | `Channel::expireUnpaidOrders()`, par canal ; `phil_commerce.order_expiry.unpaid_hours` pour les commandes d'aucun canal que la boutique connaît |
| Jours avant qu'une commande expirée soit supprimée | 30 | `Channel::expireUnpaidOrders()`, par canal ; `phil_commerce.order_expiry.delete_after_days` de même |

`null` désactive l'un ou l'autre délai pour un canal. Les valeurs par défaut sont actives, si bien qu'une boutique
minimise ce qu'elle garde sans rien faire.

```php
// $expiry: Phil\CommerceBundle\Application\OrderExpiry, from the container, run on a schedule
$sweep = $expiry->sweep($now);
$entityManager->flush();

$sweep->expired; // the order numbers expired
$sweep->deleted; // the order numbers deleted
```

- **Impayée** veut dire sans aucun paiement qui ait jamais tenu de l'argent : une commande dont tous les paiements ont
  échoué, ont été annulés ou abandonnés est impayée ; une commande avec un paiement en cours, autorisé, capturé,
  remboursé ou contesté ne l'est pas.
- **Une vente différée n'expire jamais** : son solde a sa propre date d'échéance ([deferred-sales.md](deferred-sales.md)).
- **Faire expirer annule la commande** par la transition `expire` du workflow, et `Order::expiredAt()` s'en souvient ;
  `OrderCancelled` l'annonce, avec `expired` vrai ([lifecycle.md](lifecycle.md#annoncer-une-annulation)).
- **Supprimer vérifie de nouveau, dans une transaction**, qu'aucun paiement n'a jamais tenu d'argent pour la commande
  et qu'elle n'a aucun reçu, puis supprime la commande avec ce qui n'appartient qu'à elle : ses paiements qui n'ont rien
  capturé, ses liens de paiement, ses changements et son journal.
- **Un balayage est borné** : au plus `$limit` commandes expirées et autant supprimées par canal, 1 000 par défaut et
  10 000 au plus ; l'application le relance jusqu'à ce qu'il ne reste rien.

Les commandes sont lues sur l'index de l'état et du moment du passage, par `ExpiringOrders`, que
`DoctrineOrderRepository` implémente.
