<!-- phil-dev source-sha256=f22dd20a4dde637996070fa433f870695b4a6239059dd5f2f4bedd5f83f32d79 -->
# Paiements

Une commande passée est payée par un ou plusieurs paiements, chacun par un mode dont la passerelle parle à un
processeur. **Le montant appartient au serveur, et l'état au processeur** : un paiement n'avance que lorsqu'une
notification vérifiée par sa passerelle le dit, jamais quand l'acheteur revient à la boutique.

| Type | Rôle |
|---|---|
| `Domain\Model\Payment` | Un paiement : la commande, le mode, le montant, le montant remboursé, l'état, la référence du processeur |
| `Domain\Payment\PaymentState` | `New`, `Processing`, `Authorized`, `Completed`, `Failed`, `Cancelled`, `Abandoned`, `Refunded`, `Disputed`, `ChargedBack` |
| `Domain\Payment\PaymentEvent` | Ce que dit une notification : `Processing`, `Authorized`, `Captured`, `Failed`, `Cancelled`, `Refunded`, `DisputeOpened`, `DisputeWon`, `DisputeLost` |
| `Domain\ValueObject\PaymentNotification` | Une notification vérifiée : son identifiant, la référence du processeur, l'événement, le montant d'un remboursement |
| `Domain\ValueObject\PaymentStart` | Ce avec quoi l'acheteur continue : la référence du processeur, une page ou un secret client |
| `Domain\ValueObject\OrderPayments` | Les paiements d'une commande additionnés : capturé, rendu, net, à payer, état |
| `Domain\Payment\OrderPaymentState` | `Unpaid`, `PartiallyPaid`, `Paid`, `PartiallyRefunded`, `Refunded` |
| `Application\Payments` | Commence les paiements, abandonne ceux que l'acheteur a laissés, applique les notifications, demande les remboursements, additionne une commande |
| `Application\Port\PaymentGateway` | Port : un processeur, implémenté par l'application ou par un paquet par processeur |
| `Infrastructure\Http\PaymentWebhookController` | Reçoit les notifications des processeurs |

## Payer une commande

```php
use Phil\CommerceBundle\Application\Payments;

// $payments: Payments, from the container; $order: a placed order
[$payment, $start] = $payments->start($order, 'card', $clock->now());          // all that is left to pay
[$deposit] = $payments->start($order, 'card', $clock->now(), $depositAmount);  // or part of it

$orders->add($order);    // nothing is committed by Payments
$entityManager->flush(); // the application commits its unit of work

// The buyer goes on with $start->redirectUrl, or the processor's script with $start->clientSecret.
```

- **Seule une commande passée est payée** ; un panier lève `PaymentRefused`.
- **Le montant vaut par défaut ce qui reste à payer** (`OrderPayments::payable()`) : le total moins chaque paiement
  qui tient ou peut encore tenir de l'argent — nouveau, en cours, autorisé, complété ou contesté, moins ce qui en a été
  remboursé. Un paiement échoué, annulé ou abandonné libère sa part. Un montant supérieur à zéro et au plus égal à ce
  reste est accepté, ce qui fait des **paiements fractionnés** une suite d'appels à `start()` ; tout autre montant
  lève `PaymentRefused`, et une autre devise lève `CurrencyMismatch`.
- **La passerelle du mode** remet le paiement à son processeur et répond avec un `PaymentStart` ; le paiement garde
  la référence du processeur et devient `Processing`.
- **Deux paiements commencés en même temps ne peuvent pas prendre tous deux la même part.** Chaque début est compté
  sur la commande (`Order::countPaymentStart()`, colonne `payments_started`), ce qui fait avancer sa version. Quand
  deux requêtes commencent un paiement sur la même copie de la commande, le second commit échoue avec
  l'`OptimisticLockException` de Doctrine et son paiement n'est pas stocké. L'application y répond comme à un
  conflit, et l'acheteur recommence sur la commande à jour. Le processeur peut avoir créé un paiement pour le début
  refusé ; aucune notification ne nommera jamais un paiement stocké pour lui, et le *webhook* répond 404 à toute
  notification qui arrive.

## Un paiement que l'acheteur a abandonné

Un paiement nouveau ou en cours tient sa part de la commande jusqu'à ce que son processeur dise qu'il a échoué ou a
été annulé. Un acheteur qui quitte la page du processeur ne fait souvent venir aucune notification ; l'application
l'abandonne donc :

```php
$payments->abandon($payment);                                       // the buyer came back without paying
$payments->abandonStale($order, $clock->now()->modify('-1 hour'));  // every new or processing payment older than that
```

- **Un paiement abandonné libère sa part** : `payable()` ne le compte plus, et une nouvelle tentative peut prendre le
  solde. `abandonStale()` retourne les paiements qu'il a abandonnés ; l'appeler avant chaque nouvelle tentative, ou
  selon un horaire, évite qu'un passage à la caisse laissé par l'acheteur bloque la commande pour toujours.
- **Seul un paiement nouveau ou en cours est abandonné** ; tout autre lève `PaymentRefused`.
- **Une notification tardive s'applique quand même.** Si le processeur capture finalement un paiement abandonné, la
  capture est enregistrée et le paiement compte de nouveau ; quand la commande a été payée autrement entre-temps,
  `OrderPayments::overpaid()` montre l'excédent à rembourser. Choisissez un délai d'`abandonStale()` plus long que
  l'expiration d'un passage à la caisse chez le processeur, pour que cela reste rare.

## Notifications

`Payments::notify(string $method, string $payload, array $headers)` demande à la passerelle du mode de vérifier et
de lire la notification, trouve le paiement par la référence du processeur, et applique l'événement.

| Issue | Quand |
|---|---|
| `Applied` | Le paiement a avancé |
| `AlreadyApplied` | L'identifiant de la notification a déjà été appliqué : une répétition ne change rien |
| `Refused` | L'événement ne s'applique pas au paiement dans son état actuel ; rien ne change |
| `UnknownPayment` | Aucun paiement du mode sous cette référence, pas encore ou pas du tout |

Une passerelle qui ne peut pas vérifier ou lire une notification lève `InvalidNotification`.

| Événement | De | Vers |
|---|---|---|
| `Processing` | `New` | `Processing` |
| `Authorized` | `New`, `Processing`, `Abandoned` | `Authorized` |
| `Captured` | `New`, `Processing`, `Authorized`, `Abandoned` | `Completed` |
| `Failed`, `Cancelled` | `New`, `Processing`, `Authorized`, `Abandoned` | `Failed`, `Cancelled` |
| `Refunded` (avec son montant) | `Completed` | `Completed`, ou `Refunded` quand il ne reste rien |
| `DisputeOpened` | `Completed` | `Disputed` |
| `DisputeWon` | `Disputed` | `Completed` |
| `DisputeLost` | `Disputed` | `ChargedBack` |

Un remboursement de plus que ce qui reste, de zéro, d'un montant négatif ou dans une autre devise que celle du
paiement est refusé comme un passage que la table ne liste pas : l'issue est `Refused`, et le *webhook* répond 204.

## Le point de terminaison des webhooks

Avec DoctrineBundle, le bundle enregistre `PaymentWebhookController`, que l'application route avec un attribut
`method` :

```yaml
# config/routes/phil_commerce.yaml
phil_commerce_payment_webhook:
    path: /commerce/webhooks/{method}
    controller: Phil\CommerceBundle\Infrastructure\Http\PaymentWebhookController
    methods: [POST]
    requirements: { method: '[a-z][a-z0-9_]{0,31}' }
```

| Réponse | Quand | Ce que fait un processeur |
|---|---|---|
| 204 | Appliquée, déjà appliquée, ou refusée par l'état du paiement | Cesse de l'envoyer |
| 400 | La passerelle ne la vérifie pas ou ne la lit pas | Réessaie, puis abandonne |
| 404 | Aucune passerelle pour le mode, ou pas encore de paiement sous la référence du processeur | Réessaie |

Le changement est validé avant le 204 : une notification dont la réception est confirmée n'est jamais perdue. Excluez
la route de la protection CSRF et de l'authentification : la vérification de la signature par la passerelle est ce
qui admet une notification.

## Remboursements

`Payments::refund(Payment $payment, Money $amount, string $idempotencyKey)` demande à la passerelle du paiement de
rembourser une partie ou la totalité de ce qui reste d'un paiement complété, avec une clé qui reste la même pour chaque
tentative d'un même remboursement. **Le remboursement est enregistré quand la notification du
processeur le confirme**, pas quand il est demandé : le processeur peut le refuser, ou prendre du temps. Un remboursement accordé par une
personne et exécuté par une autre passe par `Refunds` ([refunds.md](refunds.md)).

## Paiements hors ligne

Un mode de `phil_commerce.offline_payments` — un chèque, un virement bancaire, de l'argent comptant remis plus
tard — se paie sans passerelle :

```php
$payment = $payments->declareOffline($order, 'cheque', $now);          // all of what is left, or part of it
$payments->confirmOffline($order, $payment, $now);                       // the money came in
$payments->cancelOffline($payment);                                      // or it never came
```

- **Un paiement déclaré retient sa part** de la commande en traitement, nommé `offline:<id>`, jusqu'à ce qu'une
  personne de la boutique le confirme ou l'annule ; `abandonStale()` ne l'abandonne jamais.
- **Seul un mode déclaré se paie hors ligne**, pour un montant de ce qui reste, jamais plus.
- **Ses remboursements sont enregistrés aussitôt** par `Payments::refund()`, puisqu'aucun processeur ne les notifiera.
- **Confirmer annonce la commande** une fois payée en entier, comme la notification d'un processeur.

La colonne `offline` de `phil_commerce_payment` indique un paiement hors ligne.

## Une commande payée en entier

Quand le paiement qui complète une commande est appliqué — la notification d'un processeur, ou un paiement hors ligne
confirmé —, `Payments` envoie `OrderPurchased`, par sa classe, une seule fois : le numéro de la commande, son canal,
l'identifiant stable de son client, son total et le moment. Un programme de fidélité, un fil d'activité ou une liste de
souhaits l'écoute sans que le commerce les connaisse ; `WishLists` le fait ([gifts.md](gifts.md#listes-de-souhaits)).
L'événement ne porte que des identifiants et le montant ; un écouteur lit ce dont il a besoin d'autre dans son propre
stockage, et un écouteur qui le confie à Messenger n'envoie que ces identifiants.

`Payments` n'annonce que s'il peut lire la commande (`OrderRepository`, câblé par le bundle) et a un répartiteur
d'événements.

## Les paiements d'une commande

`Payments::summary(Order)` retourne `OrderPayments` :

| Membre | Contient |
|---|---|
| `total` | Le total de la commande |
| `captured()` | Ce que les processeurs ont capturé, quoi qu'il lui soit arrivé depuis |
| `returned()` | Ce qui est revenu : les remboursements, et les contestations perdues |
| `net()` | Le capturé moins le rendu |
| `payable()` | Ce qu'un nouveau paiement peut encore demander, jamais sous zéro |
| `overpaid()` | Ce que les paiements qui comptent tiennent au-delà du total : une commande modifiée à la baisse après avoir été payée |
| `state()` | `Unpaid` quand rien n'a été capturé ; `PartiallyPaid` ou `Paid` tant que rien n'est revenu ; `PartiallyRefunded` ou `Refunded` une fois que quelque chose est revenu |

## Stockage

Avec DoctrineBundle, un paiement est une rangée de `phil_commerce_payment`, avec l'alias `PaymentRepository` vers
`DoctrinePaymentRepository`.

| Colonne | Type | Contient |
|---|---|---|
| `id` | `CHAR(36)`, clé primaire | Un UUID ordonné dans le temps (v7) |
| `order_number` | `VARCHAR(64)`, indexé | La commande payée |
| `method` | `VARCHAR(32)` | Le mode, tel que le nomme sa passerelle |
| `amount`, `refunded` | `BIGINT` | En unités mineures |
| `currency` | `CHAR(3)` | La devise de la commande |
| `state` | `VARCHAR(16)` | L'état du paiement |
| `external_reference` | `VARCHAR(255)`, nullable ; unique avec `method` | La référence du processeur |
| `applied_notifications` | JSON | Les identifiants des notifications appliquées |
| `offline` | `BOOLEAN`, `false` par défaut | Si le paiement se fait hors ligne |
| `created_at` | `DATETIME` | Quand le paiement a été créé |
| `version` | `INTEGER` | Avancée par chaque écriture ; deux notifications appliquées en même temps ne peuvent pas l'emporter toutes les deux |

## Le limiteur des tentatives de paiement

`Payments::start()` n'appelle pas le limiteur : l'application décide de la tentative avec `PaymentAttemptLimiter`
avant de commencer un paiement, et l'enregistre après ([payment-attempts.md](payment-attempts.md)).
