# Reference

Every public type of the package, with its role.

## Domain

| Type | Role |
|---|---|
| `Domain\ValueObject\PaymentReceipt` | A payment receipt or a credit note as issued: numbers, issuer, lines, charges, taxes, subtotal, total. |
| `Domain\ValueObject\ReceiptLine` | A line: description, quantity, unit price, amount, its taxes, its options shown and pending. |
| `Domain\ValueObject\ReceiptCharge` | An amount other than lines and taxes, with its label. |
| `Domain\ValueObject\TaxAmount` | A tax by rate code, its amount, and whether the prices included it. |
| `Domain\ValueObject\Party` | A name and the lines of an address. |
| `Domain\Payment\PaymentDocument` | `Receipt` or `CreditNote`. |
| `Domain\ValueObject\Donation` | A gift as an official donation receipt states it; `eligibleAmount()`. |
| `Domain\ValueObject\DonationReceipt` | A donation with its serial number and date of issue; the tax authority's name and website. |
| `Domain\Donation\GiftKind` | `Cash` or `NonCash`. |
| `Domain\Repository\DonationReceiptRepository` | Keeps the donation receipts. |
| `Domain\Exception\ReceiptEmailException` | Every exception of the package. |
| `Domain\Exception\InvalidReceipt` | A value refused, naming the rule, never the personal data. |
| `Domain\Exception\ReceiptNotFound` | A receipt or a recipient not found when the email is built. |
| `Domain\Exception\NotTransactional` | A rendered receipt refused by `TransactionalCheck`. |
| `Domain\Exception\PdfUnavailable` | A PDF asked for without a printer. |

## Application

| Type | Role |
|---|---|
| `Application\ReceiptEmails` | Builds the `Email` of a payment or a donation receipt from its number. |
| `Application\ReceiptMessage` | The template key, the locale and the variables of a receipt's email. |
| `Application\RenderedReceipt` | The subject, preheader, HTML, plain text and images rendered. |
| `Application\InlineImage` | An image attached inline, shown as `cid:<name>`. |
| `Application\Recipient` | The address and the locale of whoever receives a receipt. |
| `Application\TransactionalCheck` | Refuses a rendered receipt without plain text, with remote content or tracked links. |
| `Application\DonationReceipts` | Issues donation receipts in their own series. |
| `Application\Port\PaymentReceiptSource` | Reads a payment receipt by its number. |
| `Application\Port\RecipientDirectory` | Gives the recipient of a receipt. |
| `Application\Port\ReceiptRenderer` | Renders a `ReceiptMessage`. |
| `Application\Port\PaymentReceiptPdf` | Prints a payment receipt. |
| `Application\Port\DonationReceiptPdf` | Prints a donation receipt. |
| `Application\Port\DonationReceiptNumbering` | Gives the next serial number of the donation receipts. |

## Infrastructure

| Type | Role |
|---|---|
| `Infrastructure\Commerce\CommercePaymentReceipts` | `PaymentReceiptSource` on the receipts of `phil/commerce-bundle`. |
| `Infrastructure\Commerce\CommerceReceiptPdf` | `PaymentReceiptPdf` with the `ReceiptPdf` of `phil/commerce-bundle`. |
| `Infrastructure\EmailTemplates\EmailTemplatesRenderer` | `ReceiptRenderer` with `phil/email-templates-bundle`; `DEFAULTS`, the directory of the default templates. |
| `Infrastructure\Pdf\PagedMediaDonationReceiptPdf` | `DonationReceiptPdf` with `phil/paged-media`. |
| `Infrastructure\Messenger\SendPaymentReceipt` | The message that asks for a payment receipt's email, by number. |
| `Infrastructure\Messenger\SendDonationReceipt` | The message that asks for a donation receipt's email, by serial number. |
| `Infrastructure\Messenger\SendPaymentReceiptHandler` | Builds and sends a payment receipt's email through a Mailer transport. |
| `Infrastructure\Messenger\SendDonationReceiptHandler` | Builds and sends a donation receipt's email through a Mailer transport. |
