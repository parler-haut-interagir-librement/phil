<!-- phil-dev source-sha256=73c5d8c26b6619a2f36940cbca931e982c10c3276444f5b043ab2ce86545afb9 -->
# Mises de côté

Une mise de côté réserve de la marchandise pour un client contre un acompte, et lui permet de payer par versements
jusqu'à une échéance.

```php
$checkout->ring($order, $session, 'cashier-7', $clock->now());
$registerPayments->recorded($order, $session, 'debit', $layaways->depositFor($order), 'A1B2', $clock->now());
$layaways->open($order, 'customer-42', dueAt: $clock->now()->modify('+60 days'), at: $clock->now());
```

L'acompte vaut `layaway_deposit` parties par million de la commande, arrondi vers le haut ; ouvrir avec un montant payé
inférieur est refusé. Seule une commande passée est mise de côté, et une seule fois : un panier, dont le total peut
encore changer, une commande annulée ou une commande déjà mise de côté lève `PosRefused`.
Chaque versement est un paiement du noyau, par n'importe quelle méthode. Une fois qu'il ne reste rien à payer,
`settle()` achève la mise de côté et la marchandise part avec le client ; `due($at)` liste les mises de côté ouvertes
dont l'échéance est passée, et `cancel()` en abandonne une, en annulant sa commande.

Réserver la marchandise relève du stock — une réservation de `phil/commerce-logistics-bundle`, si l'application
l'utilise —, tout comme le remboursement des versements d'une mise de côté annulée, selon la politique propre à
l'application.
