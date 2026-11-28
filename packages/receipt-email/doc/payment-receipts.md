# Payment receipts

A payment receipt's email is the receipt its issuer numbered, read again when the email is built: the package
computes no amount. `PaymentReceipt` holds what the email shows.

| Part | Holds |
|---|---|
| `document` | `PaymentDocument::Receipt` or `PaymentDocument::CreditNote`. |
| `number`, `orderNumber`, `creditedReceipt` | The receipt's number in its series, the order's, and for a credit note the receipt it credits; 1 to 64 visible ASCII characters each. |
| `issuedAt`, `issuer`, `taxNumbers` | The date of issue, the issuer's name and address, its registration number for each tax (`GST` → `123456789 RT0001`). |
| `lines` | 1 to 500 `ReceiptLine`: the description, the quantity, the unit price, the amount, the taxes charged on the line, the options shown (`Colour: Blue`) and the labels of those still to draw. |
| `charges` | At most 500 `ReceiptCharge`: shipping, fees, discounts, tips, each with its label and its signed amount. |
| `taxes` | At most 500 `TaxAmount`, each tax summed over the receipt by rate code, marked when the prices included it. |
| `itemsTotal`, `total` | The subtotal of the lines and the total, as issued. |

Every amount is a `Phil\Money\Money` in the currency of the total; another currency is refused. `addedTaxes()` and
`includedTaxes()` split the taxes; `includedTotal()` sums those the prices included, `null` when the taxes were
added. A tax the prices included is shown and never added to the total.

## Credit notes

A credit note's amounts are negative, as its issuer signs them: the lines, their taxes, the charges, the taxes and the
total. Its unit prices stay positive. It names the receipt it credits; a receipt names none, and either is refused
otherwise.

## The receipts of phil/commerce-bundle

`Infrastructure\Commerce\CommercePaymentReceipts` implements `PaymentReceiptSource` on the `ReceiptRepository` of
`phil/commerce-bundle`, and `read()` turns one of its `Receipt` into a `PaymentReceipt`:

- each line keeps its description, quantity and unit price, and its subtotal as its amount;
- the tax adjustments of a line become its taxes, a neutral one marked as included;
- an option is shown as `label: value`, by its code when it has no label; one still to draw — a value drawn after
  payment — is listed by its label among the line's pending options;
- the adjustments other than taxes become the charges; a neutral one is left out, as the receipt's PDF leaves it out;
- `Receipt::taxes()`, `Receipt::includedTaxes()`, `Receipt::itemsTotal()` and `Receipt::total()` give the taxes and the
  totals.

## The PDF

`ReceiptEmails::payment($number, attachPdf: true)` attaches the receipt's PDF, named `receipt-<number>.pdf`, every
character other than letters, digits, `.`, `_` and `-` replaced by `_`. The PDF comes from the `PaymentReceiptPdf`
given to `ReceiptEmails`; without one, asking for it throws `PdfUnavailable`.

`Infrastructure\Commerce\CommerceReceiptPdf` prints with the `ReceiptPdf` of `phil/commerce-bundle`. Its printers
are chosen by the recipient's locale, then by its language, then the default one:

```php
use Phil\CommerceBundle\Infrastructure\Pdf\ReceiptPdf;
use Phil\ReceiptEmail\Infrastructure\Commerce\CommerceReceiptPdf;

$pdf = new CommerceReceiptPdf($receiptRepository, new ReceiptPdf(), [
    'fr' => new ReceiptPdf(['receipt' => 'Reçu', 'credit_note' => 'Note de crédit', 'total' => 'Total']),
]);
```
