<!-- phil-dev source-sha256=a8ea628531869c50f87be7ab513187b320b1bef0dcf99106b802ecca0946a5e5 -->
# Notes de crédit et bordereaux

## La note de crédit d'un retour

```php
$note = $entityManager->wrapInTransaction(fn () => $creditNotes->issue($return->id, $clock->now()));
```

`ReturnCreditNotes::issue()` émet la note de crédit d'un retour dont la marchandise est revenue — reçu ou réglé —,
pour exactement ce qu'il rend. Les notes de crédit du noyau, `Receipts::credit()`, renversent des lignes entières ;
celle d'un retour renverse une partie de ligne, ses frais de remise en stock, les promotions spéciales qu'il brise et
la livraison qu'il rend.

| Partie de `ReturnCreditNote` | Ce qu'elle dit |
|---|---|
| `number` | Pris dans la série de notes de crédit du noyau, si bien que les notes de crédit des reçus restent une seule suite sans trou |
| `returnId`, `orderNumber`, `receiptNumber`, `issuedAt`, `currency` | Ce qu'elle crédite, et quand |
| `lines()` | Chaque ligne revenue : sa référence, sa description telle que le reçu l'a vendue, ses unités et celles de la ligne, son montant — sa part de ce qu'a coûté la ligne, taxes comprises —, la taxe que ce montant contient, ses frais de remise en stock |
| `specialDiscount()` | Les rabais des promotions spéciales que le retour brise, jamais plus que ce que donnent les lignes |
| `delivery()` | Les frais de livraison rendus |
| `total()` | Les lignes moins leurs frais, moins les promotions spéciales, plus la livraison : la valeur du retour |

Les montants sont positifs : ce qui est rendu. Une unité reçue `Missing` n'est pas créditée.

- **Une note par retour.** Un second `issue()` est refusé, en nommant la première note.
- **Une ligne que le noyau a créditée en entier n'est pas créditée de nouveau.** Un retour d'une ligne qu'une note du
  noyau a déjà créditée est refusé, en nommant cette note.
- **Le numéro est pris dans une transaction.** Ce dont dépend la note est vérifié avant que le numéro soit pris, si
  bien qu'une note refusée n'en utilise jamais un, puis de nouveau une fois pris, sous le verrou de la série : une note
  qu'une autre transaction a validée entre-temps est vue.
- **Une commande sans reçu n'a rien à créditer.**

Une note est une rangée de `phil_commerce_return_credit_note` : `number` sa clé, `return_id` unique, `order_number`
indexé, `receipt_number`, `issued_at`, `currency`, ses `lines` en JSON, `special_discount` et `delivery` en unités
mineures.

## Le bordereau de retour

```php
$pdf = $entityManager->wrapInTransaction(fn () => $slips->print($return->id, $clock->now(), 'fr'));   // the order's language
```

Le bordereau va dans le colis d'un retour autorisé : son numéro, la commande et le retour, le transporteur de
l'étiquette et son numéro de suivi quand il y en a une, puis la description, les unités et la raison de chaque ligne,
et ce qu'il faut faire du bordereau.

| Membre de `ReturnSlips` | Ce qu'il fait |
|---|---|
| `slipFor($returnId, $at)` | Le `ReturnSlip` d'un retour autorisé ; son premier appel numérote le bordereau dans la série `return_slip` du registre de documents du noyau, gardé sur le retour, `slipNumber()`, et les appels suivants le gardent |
| `print($returnId, $at, $locale)` | Le bordereau produit en PDF par le `ReturnSlipRenderer`, dans la locale donnée |

La série `return_slip` est déclarée au registre du noyau par `ReturnDocumentTypes`, avec le préfixe et les chiffres de
`phil_commerce_returns.slips` — `RS-` et 8 par défaut — ; ses numéros se suivent sans trou, si bien que la première
impression s'exécute dans une transaction.

`PagedMediaReturnSlips` produit le bordereau avec l'écrivain natif de `phil/paged-media` quand il est installé : une
page Letter, davantage quand les lignes l'exigent, en Helvetica. Ses mots sont les clés `phil_commerce_returns.slip.*`
et `phil_commerce_returns.reason.*` du domaine `phil_commerce_returns`, traduites en anglais et en français dans
`translations/` ; sans traducteur, il écrit en anglais. Une application produit son propre bordereau en faisant
pointer `ReturnSlipRenderer` vers son service ; sans moteur de rendu, `ReturnSlips` n'est pas enregistré.
