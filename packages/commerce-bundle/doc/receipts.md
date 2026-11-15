# Receipts and credit notes

A placed order gets **one receipt**: a copy of what was sold, as it stood when issued. A receipt is never edited:
**a credit note reverses it**, in whole or for some of its lines. Both are numbered in a series of their own,
**sequential and without gaps**.

| Type | Role |
|---|---|
| `Domain\Model\Receipt` | A receipt or a credit note: number, kind, order, issuer, lines, adjustments, taxes, totals |
| `Domain\Receipt\DocumentKind` | `Receipt`, `CreditNote` |
| `Domain\ValueObject\Issuer` | Who issues it: name, address lines, a registration number per tax |
| `Application\Receipts` | Issues the receipt of an order and the credit notes of a receipt |
| `Application\Port\DocumentNumbering` | Port: the next number of a series |
| `Domain\Repository\ReceiptRepository` | Finds and stores receipts and credit notes |

## Configuration

```yaml
# config/packages/phil_commerce.yaml
phil_commerce:
    receipts:
        issuer:
            name: 'Le Magasin inc.'
            address: ['1 rue Principale', 'Québec QC  G1R 1A1']
            tax_numbers: { GST: '123456789 RT0001', QST: '1234567890 TQ0001' }
        receipt_prefix: 'R-'       # the default
        credit_note_prefix: 'CN-'  # the default
        number_digits: 8           # the default: R-00000001
```

Receipts are off until `phil_commerce.receipts` is set; an issuer with a name is then required, and the build
fails without one. The issuer is copied onto each receipt, so a change of address never reaches a receipt already
issued.

## Issuing

```php
use Phil\CommerceBundle\Application\Receipts;

// $receipts: Receipts, from the container; $order: a placed order
$receipt = $entityManager->wrapInTransaction(fn () => $receipts->issue($order, $clock->now()));

$receipt->number;          // "R-00000001"
$receipt->lines();         // the lines as sold
$receipt->taxes();         // ["GST" => 1.50, "QST" => 2.99]: each tax summed over the lines, by its code
$receipt->total();
```

- **Only a placed order gets a receipt, and only one**: a cart or a second receipt throws `ReceiptRefused`.
- **Issue inside a transaction.** The number is taken under a lock that lasts until the transaction ends: two
  receipts issued at once wait for each other, and a receipt rolled back gives its number back. Outside a
  transaction, `DoctrineDocumentNumbering` throws a `LogicException`.
- **A refused document takes no number**: the checks run before the series moves on.
- **Two issues for the same order at once still give one receipt.** The checks run again once the number is taken,
  under the series' lock: the second issue, which waited for the first, reads its receipt, throws
  `ReceiptRefused`, and its transaction rolls back with the number. The same holds for two credit notes of the same
  lines.

## Crediting

```php
$note = $entityManager->wrapInTransaction(fn () => $receipts->credit($receipt, $clock->now()));          // every line left
$part = $entityManager->wrapInTransaction(fn () => $receipts->credit($receipt, $clock->now(), [$line]));  // some lines, whole
```

- **A credit note copies the lines it credits, each with its own adjustments** — its taxes, its promotions — and
  counts every amount negatively: `$note->total()` is the negation of what it credits.
- **The order's own adjustments** — shipping, fees — go with the note that credits the last line left.
- **A line is credited once**: a line already credited, a line the receipt lacks, a note with no line, or a note
  of a credit note throws `ReceiptRefused`.
- **Lines are credited whole.** Crediting part of a line's quantity, for a return of some units, is not supported
  yet.

## What a receipt shows

| Member | Holds |
|---|---|
| `number`, `kind`, `orderNumber`, `creditedReceipt`, `issuedAt` | Its identity; `creditedReceipt` is the receipt a credit note reverses |
| `currency`, `issuer` | The order's currency; the issuer as it was |
| `lines()`, `adjustments()` | As they were on the order, configurations included, quantities and amounts positive |
| `itemsTotal()`, `adjustmentsTotal(?AdjustmentType)`, `total()` | Negative on a credit note; neutral adjustments left out |
| `taxes()` | Each tax by its code, neutral ones included, negative on a credit note |
| `includedTaxes()` | The taxes the prices included, by code, negative on a credit note; empty when the taxes were added |

## The PDF invoice

`ReceiptPdf::render(Receipt)` returns a receipt or a credit note as a PDF: the issuer, its address and registration
numbers, the document's number and date, the order, the receipt a credit note reverses, each line with its options
below it, the subtotal, the
adjustments other than taxes, each tax by its code, and the total, amounts in the receipt's currency. A long
receipt continues on new pages, the table's header repeated. A description too long for its column goes on over
the lines below it, cut between words, and never runs into the quantity and the amounts.

Each option of a configured line is printed below the line, stood in: its label and what it holds, with the label
`option` (`%s: %s`), or `to_draw` (`drawn after payment`) for a value not drawn yet ([options.md](options.md#on-receipts)).

When the prices included the taxes, the lines and the total are the prices paid, and the taxes come after the
total: each by its code with the label `included` (`%s included`), then their sum with `taxes_included` (`Taxes
included`). A French receipt passes `['included' => '%s incluse', 'taxes_included' => 'Taxes incluses']`.

```php
return new Response($receiptPdf->render($receipt), 200, ['Content-Type' => 'application/pdf']);
```

The labels are English; an application printing in another language declares its own service with its labels —
`new ReceiptPdf(['receipt' => 'Reçu', 'credit_note' => 'Note de crédit', ...])` — and may print on A4,
`PageSize::a4()`. The writer behind it is the native `Document` of `phil/paged-media`, with its streams compressed.
By default the receipt is printed in Helvetica and Helvetica Bold, in Windows-1252, with nothing embedded: the
characters Windows-1252 lacks but labels and prices commonly hold print as their nearest equivalent — the minus sign
`−` and the non-breaking hyphen as `-`, the narrow no-break space of French as a no-break space, the thin spaces as
spaces —, and any other one as `?`. An application whose names or labels need another script passes a TrueType font
and its bold, which the document embeds reduced to the glyphs it prints:

```php
use Phil\PagedMedia\Font\TrueTypeFont;

new ReceiptPdf($labels, null, TrueTypeFont::fromFile($regular), TrueTypeFont::fromFile($bold));
```

A receipt kept for the years the law asks is printed as PDF/A-2u, which embeds its fonts and so needs the TrueType
pair; the document carries the receipt's title, its issuer and its date either way:

```php
use Phil\PagedMedia\Pdf\Conformance;

new ReceiptPdf($labels, null, TrueTypeFont::fromFile($regular), TrueTypeFont::fromFile($bold), Conformance::PdfA2u);
```

Other mentions the law asks of a receipt are the application's.

## Storage

With DoctrineBundle, a receipt or a credit note is one row of `phil_commerce_receipt`, and each series one row of
`phil_commerce_document_sequence`.

| Column | Type | Holds |
|---|---|---|
| `number` | `VARCHAR(32)`, primary key | The document number |
| `kind` | `VARCHAR(16)` | `receipt` or `credit_note` |
| `order_number` | `VARCHAR(64)`, indexed | The order |
| `credited_receipt` | `VARCHAR(32)`, nullable, indexed | The receipt a credit note reverses |
| `issued_at` | `DATETIME` | When it was issued |
| `currency` | `CHAR(3)` | The order's currency |
| `issuer` | JSON | Name, address, registration numbers |
| `lines`, `adjustments` | JSON (`phil_commerce_order_lines`, `phil_commerce_adjustments`) | As on the order |

| Column of `phil_commerce_document_sequence` | Type | Holds |
|---|---|---|
| `series` | `VARCHAR(16)`, primary key | `receipt` or `credit_note` |
| `last_number` | `BIGINT` | The last number given |
