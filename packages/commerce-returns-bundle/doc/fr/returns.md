<!-- phil-dev source-sha256=972bf8965edef582ea225cb80acc785a8ce525c83a67fe514d81b843ef02bc0c -->
# Retours

## Le parcours d'un retour

```text
requested → authorized → received → resolved
requested, authorized → awaiting information → requested
requested, awaiting information, authorized → rejected, cancelled
authorized → expired
```

```php
$return = $returns->request($order, [
    ['line' => 'product:TEA-1', 'quantity' => 2, 'reason' => ReturnReason::Defective],
    ['line' => 'product:MUG', 'quantity' => 1, 'reason' => ReturnReason::ChangedMind],
], $clock->now());

$returns->authorize($return->id, $clock->now());          // or reject($id, 'Opened and used', $at), cancel($id, $at),
                                                          // askForInformation($id, 'A photo, please', $at)
$returns->receive($return->id, [
    'product:TEA-1' => ItemCondition::Resellable,
    'product:MUG' => ItemCondition::Damaged,
], $clock->now());
$settlement = $returns->refund($return->id, $clock->now());   // or credit($id, $customer, $at), exchange($id, $newOrder, $at)
```

Une ligne est nommée comme la commande la nomme, `type:id`. Une demande est refusée pour une commande qui n'est pas
passée, au-delà de `window_days`, pour une ligne que la commande n'a pas, pour une partie d'un de ses ensembles, ou
pour plus d'unités qu'il n'en reste une fois comptés les autres retours de la commande — sauf ceux qui sont refusés,
annulés ou expirés.

Au comptoir, les mêmes étapes se suivent d'un coup.

| Étape | Ce qu'elle fait |
|---|---|
| `request($order, $lines, $at, $requester)` | Évalue les lignes et retient leurs unités ; un demandeur — l'identifiant stable du client — est soumis à la limite de débit quand `rate_limiter` en nomme une |
| `askForInformation($id, $note, $at)` | Depuis demandé ou autorisé : la note dit ce dont la boutique a besoin, en 1 à 255 caractères ; `provideInformation($id, $at)` le remet à l'état demandé |
| `authorize()`, `reject()`, `cancel()` | Un refus dit pourquoi ; un retour en attente d'information peut être refusé ou annulé |
| `expire($now)` | Fait expirer les retours autorisés depuis plus de `expiry_days` dont la marchandise n'est jamais venue, et libère leurs unités ; une tâche planifiée l'appelle ; rien quand `expiry_days` est `null` |
| `label($id, $at)` | Demande à l'adaptateur `ReturnLabels` l'étiquette d'un transporteur pour un retour autorisé, et garde son transporteur, son numéro de suivi et son fichier |
| `receive()`, puis `refund()`, `credit()` ou `exchange()` | Voyez plus bas |
| `anonymize($id)` | Efface la note — un refus, une question —, en gardant ce dont les comptes ont besoin |

### Le délai

`window_days` compte depuis le passage de la commande ou, avec `window_from: shipped`, depuis sa dernière expédition,
que connaît `phil/commerce-logistics-bundle` ; une commande dont rien n'a encore été expédié compte depuis son passage.
Le dernier moment du délai est compris.

## Ce que vaut un retour

Chaque unité vaut sa part de ce qu'a coûté sa ligne de commande : le sous-total de la ligne, ses promotions et ses
taxes — sauf les ajustements neutres, déjà compris dans le prix —, divisé entre ses unités à l'unité mineure la plus
proche. Une ligne retournée en plusieurs retours est partagée de sorte que les parts s'additionnent pour donner la
ligne : chaque retour vaut la part des unités jusqu'aux siennes comprises, moins la part des unités que gardent encore
les retours précédents. Une ligne de 2,00 pour 3 unités, retournée une unité à la fois, donne 0,67, 0,66 et 0,67. La
livraison, les pourboires et tout ce qui n'est sur aucune ligne ne sont pas rendus.

Les frais de remise en stock, `restocking_fee` parties par million, sont retenus sur les lignes dont la raison n'est pas
la faute de la boutique — `ChangedMind`, `Other` ; jamais sur `Defective`, `Damaged`, `WrongItem` ou `NotAsDescribed`.
Une unité reçue `Missing` ne vaut rien.

### Les ensembles

Un ensemble — une boîte assemblée une fois et vendue comme un seul produit, un produit composé de
`phil/commerce-catalog-bundle` — est une seule ligne de la commande : ses unités reviennent en entier. Une demande qui
nomme une de ses parties est refusée :

```text
In the order A-1, product:DICE came in the set product:BOX: the set comes back whole, never a part of it.
```

Le port `SetParts` dit quelles lignes sont des ensembles ; `CatalogSetParts` lit le catalogue quand il est enregistré.

### Les promotions spéciales

Une promotion spéciale — un prix pour des produits achetés ensemble, un prix de lot de `phil/commerce-promotions-bundle`
— garde une ligne par produit, chacune avec sa part du rabais. Un produit retourné seul est rendu à sa valeur moins le
rabais de la promotion, qui disparaît : les produits gardés sont payés en entier.

| Commande | Retour | Rendu |
|---|---|---|
| Un livre à 30,00 et des dés à 20,00, 40,00 ensemble | Le livre | 30,00 − 10,00 = 20,00 ; les dés coûtent 20,00 |
| La même | Puis les dés | 20,00 ; 40,00 en tout, ce qui a été payé |
| Deux paires, 80,00 | Un livre | 30,00 − 10,00 : une promotion brisée, l'autre gardée |

- **La valeur de la ligne** est sa part de ce qu'a coûté la ligne de commande, le rabais de la promotion remis, plus la
  taxe que ce rabais a épargnée.
- **Le retour se voit imputer** la part du rabais de chaque promotion, taxes comprises, que brisent les unités revenues
  jusqu'ici : la part de la plus retournée de ses lignes, arrondie à la hausse, moins ce que les retours précédents
  qui retiennent encore leurs unités se sont vu imputer. Elle est gardée sur le retour, par l'origine de
  l'ajustement, `specialCharges()`.
- **Les imputations ne font jamais passer les lignes sous zéro** : un retour ne vaut jamais moins que rien à cause
  d'elles.

Le port `SpecialDiscounts` dit quels ajustements de promotion sont des promotions spéciales ; `PromotionSpecials` lit
les promotions quand elles sont enregistrées. Sans lui, chaque rabais reste sur sa ligne, comme dans la part décrite
plus haut.

### La livraison

Les frais de livraison — les ajustements de livraison de la commande qui ne sont sur aucune ligne, leurs exonérations
comprises, sauf des frais déjà compris dans les prix — sont rendus une fois par commande, avec le premier retour
retenant des unités que `delivery_refund` laisse les rendre :

| `delivery_refund` | Rendue avec |
|---|---|
| `never` | Aucun retour |
| `shops_fault`, la valeur par défaut | Un retour dont chaque ligne est la faute de la boutique, ou celui qui rapporte chaque unité restante de la commande |
| `whole_order` | Seulement le retour qui rapporte chaque unité restante de la commande |

Elle s'ajoute à la valeur du retour, `deliveryRefund()` ; un retour refusé, annulé ou expiré laisse le suivant la
rendre.

## Remise en stock

À la réception, chaque service `Restocking` est appelé pour remettre en place les unités reçues `Resellable`. Le module
ne garde lui-même aucun stock. Il reçoit la référence de la ligne de commande, relue par
`SellableReference::fromValue()` : l'empreinte d'une ligne configurée y reste, et ce qui retourne sur la tablette est
son vendable `unconfigured()`.

## Règlement

| Règlement | Ce qui se passe |
|---|---|
| `refund()` | La valeur retourne aux paiements terminés de la commande, le plus récent d'abord, chacun jusqu'à ce qu'il peut encore rembourser — sauf les paiements de méthode `exchange` ; ce qu'aucun ne peut prendre devient le manque du règlement |
| `credit()` | La valeur devient le crédit en magasin du client, par `phil/commerce-stored-value-bundle` |
| `exchange()` | La valeur paie une commande passée dans la même devise, comme un paiement du noyau de méthode `exchange` ; ce que cette commande ne prend pas est remboursé |

`Settlement` donne la valeur, chaque remboursement et son paiement, le manque, le crédit, le paiement d'échange et les
remboursements qui ont échoué. Les remboursements passent par `Payments::refund()` du noyau, chacun sous la clé
d'idempotence `<id du retour>:<id du paiement>`, la même à chaque essai, pour qu'un processeur sollicité de nouveau ne
rembourse qu'une fois : le remboursement d'un processeur est enregistré quand arrive sa notification.

- **Un paiement de méthode `exchange` n'est jamais remboursé en argent.** Sa valeur vient d'un retour précédent ; le
  rembourser ne déplacerait rien. Sa part reste dans le manque, que l'application rend comme elle le décide — en
  crédit en magasin, par exemple.
- **Un processeur qui échoue est sauté, sans retour arrière.** Les remboursements envoyés aux autres processeurs ne
  peuvent pas être rappelés : l'échec va donc dans `Settlement::$failures` — le paiement, le montant et l'exception —
  et sa part passe aux paiements suivants, ou au manque. Le retour est réglé avec les remboursements qui sont partis,
  et l'application retente elle-même la partie qui a échoué, avec `Payments::refund()` et une clé d'idempotence à
  elle.

La note de crédit d'un retour, partie de ligne comprise, est `ReturnCreditNotes::issue()` — voyez
[Notes de crédit et bordereaux](credit-notes-and-slips.md).

## Étiquettes

`label()` demande à l'adaptateur `ReturnLabels` de l'application — celui d'un transporteur, qui connaît le compte de la
boutique et l'adresse du client — l'étiquette d'un retour autorisé. Le `ReturnLabel` qu'il rend — un transporteur et un
numéro de suivi en ASCII visible, et la référence propre à l'adaptateur vers le fichier de l'étiquette — est gardé sur
le retour, `label()`, et imprimé sur son bordereau. Sans adaptateur, `label()` est refusé.

## Événements

Avec un répartiteur d'événements, chaque étape est annoncée, avec des identifiants, des états et des montants
seulement ; un écouteur — le stock, une notification, les comptes — lit le reste dans son stockage.

| Événement | Quand |
|---|---|
| `ReturnStateChanged` | Chaque étape : demandé (depuis aucun état), autorisé, renvoyé pour information, demandé de nouveau, refusé, annulé, reçu, réglé, expiré — le retour, la commande, l'état avant et après, le moment |
| `ReturnSettled` | Après le `ReturnStateChanged` du règlement : le retour, la commande, le règlement, la valeur |
| `ReturnCreditNoteIssued` | Une note de crédit émise : son numéro, le retour, la commande, son total |
