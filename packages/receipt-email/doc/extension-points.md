# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

## Ports

| Port | Implementations | Implement it to |
|---|---|---|
| `Application\Port\PaymentReceiptSource` | `Infrastructure\Commerce\CommercePaymentReceipts` | read the receipts of another system, building each `PaymentReceipt` from its own amounts |
| `Application\Port\RecipientDirectory` | none: the application's | give the address and the locale of the buyer of an order or of a donor |
| `Application\Port\ReceiptRenderer` | `Infrastructure\EmailTemplates\EmailTemplatesRenderer` | render with another template engine |
| `Application\Port\PaymentReceiptPdf` | `Infrastructure\Commerce\CommerceReceiptPdf` | print the PDF of a payment receipt |
| `Application\Port\DonationReceiptPdf` | `Infrastructure\Pdf\PagedMediaDonationReceiptPdf` | print the PDF of a donation receipt |
| `Application\Port\DonationReceiptNumbering` | none: the application's | number the donation receipts in their series, on its storage |
| `Domain\Repository\DonationReceiptRepository` | none: the application's | keep the donation receipts |

`DonationReceiptNumbering::next()` returns 1 to 32 visible ASCII characters, unique in the series, sequential and
without gaps; it runs inside the transaction that keeps the receipt, and locks the series until it ends.

## Options

- `ReceiptEmails`: the sender, the `TransactionalCheck`, the PDF printers.
- `ReceiptMessage::payment()` and `donation()`: the template key, `receipt` and `donation-receipt` by default.
- `EmailTemplatesRenderer`: the site, the current one by default.
- `CommerceReceiptPdf`: the default printer and one per locale or language.
- `PagedMediaDonationReceiptPdf`: the labels per locale or language, the page size, the fonts, the PDF/A conformance,
  the image of the signature.
- The default templates: a template of the same key and locale in a directory listed before
  `EmailTemplatesRenderer::DEFAULTS` replaces the package's.
