# phil/receipt-email

[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/parler-haut-interagir-librement/receipt-email/badge)](https://scorecard.dev/viewer/?uri=github.com/parler-haut-interagir-librement/receipt-email)

**Email each payment its numbered receipt and each gift its official tax receipt: amounts as issued, a plain-text
part, the PDF on request, no tracking.**

phil/receipt-email is a plain PHP library on `phil/money` and Symfony Mime. It builds the email of a **payment
receipt** or a **credit note** from the receipt its issuer numbered — the lines, the taxes of each line, the other
charges, the taxes summed by rate code, the subtotal and the total, every amount a `Money` copied as issued and never
computed again —, and the email of an **official donation receipt**, a document of its own with its own series of
serial numbers, the statements the Canada Revenue Agency asks of an official receipt, and the advantage the donor
received taken off the eligible amount.

A receipt stays a **transactional message**. The core turns a receipt into a **`ReceiptMessage`** — a template key,
a locale and typed variables —, a **`ReceiptRenderer`** port renders it, and **`TransactionalCheck`** refuses the
result before it is sent unless it has a plain-text part beside its HTML, loads nothing — every image embedded and
shown as `cid:`, no tracking pixel, no script, frame or remote style sheet — and carries no link tagged for tracking.
Nothing in the variables but the receipt and the recipient's name: no offer slipped in. **`ReceiptEmails`** builds the
Symfony Mime `Email`, marked `Auto-Submitted: auto-generated`, with the PDF attached only when the application asks
for it.

The adapters are optional: **`CommercePaymentReceipts`** and **`CommerceReceiptPdf`** read and print the receipts of
`phil/commerce-bundle`; **`EmailTemplatesRenderer`** renders with `phil/email-templates-bundle` — templates per site
and locale, sandboxed Twig —, from the default templates `receipt` and `donation-receipt` the package ships in English
and in French; **`PagedMediaDonationReceiptPdf`** prints a donation receipt with `phil/paged-media`. Two Messenger
messages, **`SendPaymentReceipt`** and **`SendDonationReceipt`**, carry a receipt's number and nothing else; their
handlers read the receipt and its recipient again in the worker and send through a Mailer transport. Its services keep
no state between requests and serve a long-running worker.

## Status

| Part | State |
|---|---|
| `PaymentReceipt`, `ReceiptLine`, `ReceiptCharge`, `TaxAmount`, `Party`, `PaymentDocument`: a receipt as issued, checked | Implemented and tested |
| `Donation`, `DonationReceipt`, `GiftKind`, `DonationReceipts`, `DonationReceiptNumbering`, `DonationReceiptRepository`: official donation receipts in their own series | Implemented and tested |
| `ReceiptMessage`, `ReceiptRenderer`, `RenderedReceipt`, `TransactionalCheck`, `ReceiptEmails`: the message, its rendering, its checks, the `Email` | Implemented and tested |
| `CommercePaymentReceipts`, `CommerceReceiptPdf`: the receipts of `phil/commerce-bundle` | Implemented and tested |
| `EmailTemplatesRenderer` and the default templates `receipt` and `donation-receipt`, English and French | Implemented and tested |
| `PagedMediaDonationReceiptPdf`: the PDF of a donation receipt | Implemented and tested |
| `SendPaymentReceipt`, `SendDonationReceipt` and their handlers: Messenger with identifiers only | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the optional packages, wiring the services. |
| [Payment receipts](doc/payment-receipts.md) | What a payment receipt's email holds, credit notes, taxes, the commerce adapters, the PDF. |
| [Donation receipts](doc/donation-receipts.md) | The official donation receipt, its statements as the Canada Revenue Agency lists them, the advantage, the series. |
| [Templates and rendering](doc/templates.md) | The message and its variables, the default templates, `phil/email-templates-bundle`. |
| [Sending](doc/sending.md) | `ReceiptEmails`, the transactional checks, Messenger and the Mailer transport. |
| [Security](doc/security.md) | What the package protects against, and what is left to the application. |
| [Extension points](doc/extension-points.md) | The ports to implement, the options, and when they are stable. |
| [Testing](doc/testing.md) | The package's tests. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/receipt-email phil/commerce-bundle phil/email-templates-bundle symfony/mailer symfony/messenger
```

```php
use Phil\ReceiptEmail\Application\ReceiptEmails;
use Phil\ReceiptEmail\Infrastructure\Commerce\CommercePaymentReceipts;
use Phil\ReceiptEmail\Infrastructure\EmailTemplates\EmailTemplatesRenderer;
use Phil\ReceiptEmail\Infrastructure\Messenger\SendPaymentReceipt;

$emails = new ReceiptEmails(
    new CommercePaymentReceipts($receiptRepository),
    $donationReceipts,               // your DonationReceiptRepository
    $recipients,                     // your RecipientDirectory: the buyer, the donor
    new EmailTemplatesRenderer($emailRenderer),
    'Shop <shop@example.com>',
);

// In the request: a message with the receipt's number only.
$bus->dispatch(new SendPaymentReceipt($receipt->number));

// In the worker, SendPaymentReceiptHandler builds the email and sends it:
$transport->send($emails->payment('R-000042'));
```

## Requirements

PHP 8.5 or later with `ext-dom`, `ext-libxml` and `ext-mbstring`, Symfony Mime 8.2 or later, and `phil/money`.
`phil/commerce-bundle`, `phil/email-templates-bundle`, `phil/paged-media`, Symfony Mailer and Symfony Messenger are
optional, each for its adapter.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
