<!-- phil-dev source-sha256=6720d2e9f9c025a535e5bf89baf9fa7fea1d0abab3a70e08e3d06ccd1231ed4d -->
# Changer de palier sur-le-champ

`Subscriptions::changeTo()` change de palier au renouvellement suivant. `Subscriptions::changeNow()` le change
maintenant : l'abonné paie la différence pour le temps qui reste, sur une commande à part, et le changement prend effet
quand cette commande est payée.

```php
$order = $subscriptions->changeNow($subscription, 'premium', $orderNumbers->next(), $taxZoneOf($subscription->subscriber), $clock->now());

if (null !== $order) {
    $orders->add($order);                                  // the core's OrderRepository
    [$payment, $start] = $payments->start($order, 'card', $clock->now());
}

// When the core applied the payment's notification
$subscriptions->paid($subscription, $order->number, $clock->now());          // the change takes effect, or
$subscriptions->paymentFailed($subscription, $order->number, $clock->now()); // the change is given up
```

## Ce qui est facturé

La commande a une ligne `subscription:<id>`, qui vend une période entière du nouveau palier au prix des résolveurs,
jamais à un prix venu d'un formulaire. `ProrationOrderProcessor`, un `OrderProcessor` du noyau, pose ensuite sur
cette ligne des ajustements de type `Proration` :

| Ajustement | Origine | Montant |
|---|---|---|
| Temps déjà utilisé | `proration:time` | Dans la même période : la part du prix du nouveau palier pour le temps déjà écoulé, retranchée |
| Crédit de la période inutilisée | `proration:credit` | La part du prix de la période en cours pour le temps qui reste, retranchée |
| Crédit reporté | `proration:carried` | Ce qu'un changement antérieur a laissé, retranché |

- **Dans la même période** — du mensuel au mensuel —, le nouveau palier est facturé pour le temps qui reste, et la
  période garde sa fin.
- **Vers un palier d'une autre période** — du mensuel à l'annuel —, le nouveau palier est facturé pour une période
  entière, qui commence au changement ; seule la part inutilisée de la période en cours est créditée.
- **Le prix de la période en cours est ce qui a été commandé pour elle** : le sous-total de la ligne avec ses options
  et après ses promotions, avant les taxes. Un palier dont le prix a changé depuis ne change pas le crédit, et un
  deuxième changement dans la même période crédite le temps qui reste au prix du premier palier choisi.
- **Le noyau taxe la ligne après son calcul au prorata**, aux taux de sa catégorie de taxe dans la zone de la
  commande : la taxe porte sur ce que l'abonné paie.
- **Les crédits ne font jamais passer la ligne sous zéro.** Ce que la commande n'utilise pas est reporté, une fois
  payée, et retranché des commandes suivantes de l'abonnement — renouvellements ou changements — jusqu'à épuisement.
  Un renouvellement dont le paiement échoue garde le crédit pour l'essai suivant.
- Chaque montant est un `Money` dans la devise de la commande, qui est celle de l'abonnement : un palier dont le prix
  est dans une autre devise est refusé.

## Arrondi

Les parts se comptent en secondes, du début de la période en cours à sa fin, et se calculent avec
`Money::allocate()` : **la part du temps qui reste est arrondie à l'unité mineure la plus proche, une égalité allant
au temps qui reste**. L'arrondi ne perd ni ne crée rien : la part du temps qui reste et celle du temps utilisé font
toujours le prix. Une devise sans décimales, le yen, est arrondie au yen : 23 jours restants sur 30 créditent 767 yens
d'une période de 1 000 yens.

## Quels changements sont calculés au prorata

| Abonnement | Ce que fait `changeNow()` | Retourne |
|---|---|---|
| Actif, la période en cours | Commande le changement, au prorata | La commande |
| Actif, le jour du renouvellement, sa période terminée | Renouvelle au nouveau palier, à son prix entier, comme `order()` | La commande |
| En attente, pas encore commandé | Commande sa première période au nouveau palier | La commande |
| À l'essai | Change de palier sur-le-champ, sans commande ; l'essai garde sa fin et donne les fonctionnalités du nouveau palier | `null` |
| Vers un palier inférieur | Attend le renouvellement suivant, comme `changeTo()`, sauf si `immediate_downgrades` est activée | `null` |
| Vers son palier actuel | Annule un changement qui attend le renouvellement | `null` |
| En souffrance, annulé, ou qui attend une commande | Refusé : la période en retard se règle, ou l'annulation se reprend, d'abord | — |

Un palier est inférieur quand son rang est plus bas. Avec `immediate_downgrades`, un changement vers un palier
inférieur est commandé et calculé au prorata de la même façon : son crédit dépasse d'ordinaire le prix du nouveau
palier, la commande est donc nulle et le reste est reporté. Une commande dont le total est nul n'a rien à prélever :
l'application la confirme sur-le-champ avec `paid()`.

## Pendant que le changement est attendu

- L'abonnement garde son palier et ses fonctionnalités jusqu'au paiement de la commande. `pendingChange()` retourne
  l'`ImmediateChange` : le palier choisi, le moment, les secondes qui restent et le crédit.
- Son renouvellement n'est pas commandé, et aucun autre changement n'est accepté, tant que la commande est attendue.
- Un paiement échoué abandonne le changement : l'abonnement reste tel qu'il était, jamais en souffrance, et son crédit
  reporté est gardé.
- Une commande qui ne peut pas être passée abandonne le changement avant que quoi que ce soit soit attendu.

## Stockage

Les colonnes que le changement ajoute à `phil_commerce_subscription` :

| Colonne | Type | Contient |
|---|---|---|
| `currency` | `CHAR(3)` | La devise de chaque montant de l'abonnement |
| `period_amount` | `BIGINT` | Le prix d'une période entière du palier actuel, tel que commandé la dernière fois, en unités mineures |
| `carried_credit` | `BIGINT` | Le crédit reporté |
| `awaited_amount`, `awaited_credit` | `BIGINT` | Le prix d'une période entière sur la commande attendue, et le crédit qu'elle retranche |
| `change_offer`, `change_at` | `VARCHAR(64)`, `DATETIME`, nullable | Le palier choisi sur-le-champ, et le moment |
| `change_seconds_left`, `change_period_seconds` | `INTEGER` | Le temps qui reste, et la durée de la période ; `null` quand le changement commence une période |
| `change_credit` | `BIGINT` | Le crédit du changement |
