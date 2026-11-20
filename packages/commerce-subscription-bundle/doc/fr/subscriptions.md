<!-- phil-dev source-sha256=c0a430ddabff43fb6e75be872cd95fe24bf745d5142465bc1209cd7a446b55c6 -->
# Abonnements

Un abonnement lie un abonné à une offre. **Chaque période est payée par une commande du noyau** : le module passe la
commande, l'application l'enregistre et prend son paiement par le noyau, puis dit à l'abonnement comment le paiement
s'est passé.

## États

| État | Accès | Atteint par |
|---|---|---|
| `Pending` | Non | L'abonnement à une offre payante sans essai |
| `Trialing` | Oui | L'abonnement à une offre avec essai |
| `Active` | Oui | L'abonnement à une offre gratuite ; une période payée |
| `PastDue` | Comme le disent les étapes de relance de son offre : toutes les fonctionnalités par défaut ([dunning.md](dunning.md)) | Le paiement d'un renouvellement a échoué ; il est retenté |
| `Ended` | Non | Annulé et sa période terminée ; la dernière tentative a échoué ; annulé alors qu'il était en attente ou en souffrance ; en attente depuis plus de `abandon_pending_after_days` |

## La boucle que tient l'application

```php
use Phil\CommerceSubscriptionBundle\Application\Subscriptions;
use Phil\CommerceSubscriptionBundle\Domain\Exception\SubscriptionRefused;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;

// When the subscriber subscribes
$subscription = $subscriptions->subscribe($accountId, 'pro', $clock->now());

// Once in a while, from a scheduled command
$refused = static fn (Subscription $subscription, SubscriptionRefused $refusal) => $logger->error($refusal->getMessage(), ['subscription' => $subscription->id]);

foreach ($subscriptions->sweep($clock->now(), $refused) as $due) {
    $order = $subscriptions->order($due, $orderNumbers->next(), $taxZoneOf($due->subscriber), $clock->now());
    $orders->add($order);                                  // the core's OrderRepository
    [$payment, $start] = $payments->start($order, 'card', $clock->now());
}
$entityManager->flush();

// When the core applied the payment's notification
$subscriptions->paid($subscription, $order->number, $clock->now());          // or
$subscriptions->paymentFailed($subscription, $order->number, $clock->now());
```

- **`sweep(at, refused, limit)`** met fin aux abonnements en attente plus vieux que
  `phil_commerce_subscription.abandon_pending_after_days` (7 par défaut) — inscrits et jamais payés —, met fin à ceux
  annulés à la fin d'une période maintenant terminée, renouvelle les gratuits sans commande — y compris un essai dont
  l'abonné est passé à un palier gratuit —, et retourne ceux dont la période doit être commandée : ceux en attente qui
  n'attendent encore aucune commande, et les essais ou les périodes terminés.
  - Chaque sorte est lue par sa propre requête ; un abonnement en attente qui attend déjà son premier paiement n'est
    pas lu du tout tant qu'il n'est pas payé, qu'il n'échoue pas pour de bon ou qu'il n'est pas abandonné.
  - **Un abonnement que le balayage ne peut pas traiter n'arrête jamais les autres** : son `SubscriptionRefused` — son
    offre disparue, un état qu'il refuse — est passé à la fonction `refused`, si elle est donnée, et le balayage
    continue.
  - `limit` plafonne chacune des trois lectures, pour un balayage exécuté par lots ; ce qui reste est lu à l'exécution
    suivante.
- **`order(subscription, number, taxZone, at)`** passe une commande du noyau avec une ligne `subscription:<id>` — la
  période suivante de cet abonnement à son palier de renouvellement, le palier choisi s'il y en a un — au prix actuel
  du palier, taxée par le noyau dans la zone donnée, et l'enregistre comme attendue. La ligne vend le palier même une
  fois retiré de la vente, et seulement tant que l'abonnement n'attend aucune commande. Un abonnement qui n'est pas dû
  est refusé avant que quoi que ce soit soit passé.
- **`paid(subscription, orderNumber, at)`** démarre la première période, ou ajoute la suivante ; un changement de
  palier prend effet. Le paiement d'une autre commande que celle attendue est refusé.
- **`paymentFailed(subscription, orderNumber, at)`** garde un abonnement en attente pour un autre essai ; un essai ou
  un renouvellement passe en souffrance, et se termine après `phil_commerce_subscription.payment_attempts` échecs (3
  par défaut), ou sur-le-champ quand l'abonné a annulé pendant que le paiement était en cours. Chaque nouvelle
  tentative est un nouveau paiement de la même commande, par le noyau.
- `Subscriptions` ne valide rien : l'application valide son unité de travail.

## Périodes

- **Un essai payé tôt** démarre sa première période à la fin de l'essai ; payé plus tard, à l'arrivée du paiement.
- **Un renouvellement payé quelques jours en retard** ne déplace pas la fin suivante : les périodes sont comptées à
  partir du point d'ancrage ([offers.md](offers.md#périodes)).
- **Un palier d'une autre période** — du mensuel à l'annuel — démarre là où se termine la dernière période payée.

## Annuler et changer de palier

| Appel | Effet |
|---|---|
| `cancel(subscription, at)` | Actif ou à l'essai : se termine à la fin de la période payée, et n'est plus dû. En attente ou en souffrance : se termine maintenant. Terminé : refusé |
| `resume(subscription)` | Reprend une annulation avant la fin de la période |
| `changeTo(subscription, offerCode)` | Le palier auquel la période suivante est commandée, dans la famille ; le palier actuel annule un changement. Refusé pour une autre famille, une autre devise, un palier retiré, un abonnement terminé ou un abonnement qui attend un paiement |
| `changeNow(subscription, offerCode, number, taxZone, at)` | Le palier changé maintenant, sur une commande à part, la partie inutilisée de la période créditée — voir [Changer de palier sur-le-champ](proration.md) |

**Annuler pendant que la commande de la période suivante attend son paiement** laisse l'issue à ce paiement, pour
qu'aucun argent ne soit prélevé pour rien : payé, la période est accordée — l'abonné garde ce qu'il a payé — et
l'abonnement se termine à sa fin ; échoué, il se termine sur-le-champ. Le balayage ne met pas fin à un abonnement
annulé tant qu'une commande est attendue. Une application qui ne veut aucun prélèvement annule le paiement de la
commande par le noyau (`Payments::abandon()`, ou le processeur), puis appelle `paymentFailed()`.

## Stockage

Avec DoctrineBundle, un abonnement est une rangée de `phil_commerce_subscription`.

| Colonne | Type | Contient |
|---|---|---|
| `id` | `CHAR(36)`, clé primaire | Un UUID ordonné dans le temps (v7) |
| `subscriber` | `VARCHAR(255)`, indexée | L'identifiant de l'abonné dans l'application |
| `payer` | `VARCHAR(255)`, nullable | Qui paie les renouvellements quand ce n'est pas l'abonné : l'acheteur d'un cadeau |
| `family`, `offer`, `next_offer` | `VARCHAR(64)` | La famille, le palier actuel, le palier choisi |
| `state` | `VARCHAR(16)`, indexée avec `period_end` | L'état |
| `anchor`, `period_end` | `DATETIME`, nullable | Le point d'ancrage des périodes, la fin de l'essai ou de la dernière période payée |
| `periods_paid`, `periods_since_anchor` | `INTEGER` | Les périodes payées en tout, et depuis le point d'ancrage |
| `period_unit`, `period_count` | `VARCHAR(8)`, `SMALLINT` | La dernière période payée |
| `cancel_at_period_end` | `BOOLEAN` | Annulé, se termine à la fin de la période |
| `awaited_order` | `VARCHAR(64)`, nullable | La commande du noyau dont le paiement est attendu |
| `failed_payments` | `SMALLINT` | Les échecs depuis le dernier paiement |
| `past_due_since` | `DATETIME`, nullable | Le premier échec du renouvellement en souffrance, à partir duquel comptent les étapes de relance de son offre |
| `created_at`, `ended_at` | `DATETIME` | Le moment où il a commencé, et celui où il s'est terminé |
| `version` | `INTEGER` | Avancée à chaque écriture |

Les colonnes d'un changement sur-le-champ et du crédit sont décrites dans
[Changer de palier sur-le-champ](proration.md#stockage).
