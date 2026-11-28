<!-- phil-dev source-sha256=ac52349271b575652ea51856fdc1c85e0ed8ef8e59a5c317040ab1f85fff3534 -->
# Reçus de paiement

Le courriel d'un reçu de paiement est le reçu que son émetteur a numéroté, relu au moment où le courriel est construit :
le paquet ne calcule aucun montant. `PaymentReceipt` contient ce que le courriel montre.

| Partie | Contient |
|---|---|
| `document` | `PaymentDocument::Receipt` ou `PaymentDocument::CreditNote`. |
| `number`, `orderNumber`, `creditedReceipt` | Le numéro du reçu dans sa série, celui de la commande et, pour une note de crédit, le reçu qu'elle crédite ; de 1 à 64 caractères ASCII visibles chacun. |
| `issuedAt`, `issuer`, `taxNumbers` | La date d'émission, le nom et l'adresse de l'émetteur, son numéro d'inscription pour chaque taxe (`GST` → `123456789 RT0001`). |
| `lines` | De 1 à 500 `ReceiptLine` : la description, la quantité, le prix unitaire, le montant, les taxes perçues sur la ligne, les options montrées (`Colour: Blue`) et les libellés de celles qui restent à tirer. |
| `charges` | Au plus 500 `ReceiptCharge` : livraison, frais, rabais, pourboires, chacun avec son libellé et son montant signé. |
| `taxes` | Au plus 500 `TaxAmount`, chaque taxe totalisée sur le reçu par code de taux, marquée lorsque les prix l'incluaient. |
| `itemsTotal`, `total` | Le sous-total des lignes et le total, tels qu'émis. |

Chaque montant est un `Phil\Money\Money` dans la devise du total ; une autre devise est refusée. `addedTaxes()` et
`includedTaxes()` séparent les taxes ; `includedTotal()` additionne celles que les prix incluaient, `null` quand les
taxes étaient ajoutées. Une taxe incluse dans les prix est affichée et jamais ajoutée au total.

## Les notes de crédit

Les montants d'une note de crédit sont négatifs, comme son émetteur les signe : les lignes, leurs taxes, les frais, les
taxes et le total. Ses prix unitaires restent positifs. Elle nomme le reçu qu'elle crédite ; un reçu n'en nomme aucun,
et l'un comme l'autre est refusé dans le cas contraire.

## Les reçus de phil/commerce-bundle

`Infrastructure\Commerce\CommercePaymentReceipts` implémente `PaymentReceiptSource` sur le `ReceiptRepository` de
`phil/commerce-bundle`, et `read()` transforme l'un de ses `Receipt` en `PaymentReceipt` :

- chaque ligne garde sa description, sa quantité et son prix unitaire, et son sous-total comme montant ;
- les ajustements de taxe d'une ligne deviennent ses taxes, un ajustement neutre étant marqué comme inclus ;
- une option est montrée sous la forme `label: value`, par son code quand elle n'a pas de libellé ; une option qui reste
  à tirer — une valeur tirée après le paiement — est listée par son libellé parmi les options en attente de la ligne ;
- les ajustements autres que les taxes deviennent les frais ; un ajustement neutre est omis, comme le PDF du reçu l'omet ;
- `Receipt::taxes()`, `Receipt::includedTaxes()`, `Receipt::itemsTotal()` et `Receipt::total()` donnent les taxes et les
  totaux.

## Le PDF

`ReceiptEmails::payment($number, attachPdf: true)` joint le PDF du reçu, nommé `receipt-<number>.pdf`, chaque caractère
autre qu'une lettre, un chiffre, `.`, `_` et `-` étant remplacé par `_`. Le PDF vient du `PaymentReceiptPdf` donné à
`ReceiptEmails` ; sans lui, le demander lève `PdfUnavailable`.

`Infrastructure\Commerce\CommerceReceiptPdf` imprime avec le `ReceiptPdf` de `phil/commerce-bundle`. Ses imprimeurs sont
choisis par la locale du destinataire, puis par sa langue, puis l'imprimeur par défaut :

```php
use Phil\CommerceBundle\Infrastructure\Pdf\ReceiptPdf;
use Phil\ReceiptEmail\Infrastructure\Commerce\CommerceReceiptPdf;

$pdf = new CommerceReceiptPdf($receiptRepository, new ReceiptPdf(), [
    'fr' => new ReceiptPdf(['receipt' => 'Reçu', 'credit_note' => 'Note de crédit', 'total' => 'Total']),
]);
```
