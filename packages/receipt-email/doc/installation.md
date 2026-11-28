# Installation

```sh
composer require phil/receipt-email
```

The package needs PHP 8.5 or later with `ext-dom`, `ext-libxml` and `ext-mbstring`, Symfony Mime 8.2 or later, and
`phil/money`. Each adapter needs the package it adapts:

| Adapter | Install |
|---|---|
| `Infrastructure\Commerce\CommercePaymentReceipts`, `CommerceReceiptPdf` | `phil/commerce-bundle` |
| `Infrastructure\EmailTemplates\EmailTemplatesRenderer` | `phil/email-templates-bundle` |
| `Infrastructure\Pdf\PagedMediaDonationReceiptPdf` | `phil/paged-media` |
| `Infrastructure\Messenger\SendPaymentReceiptHandler`, `SendDonationReceiptHandler` | `symfony/mailer`, `symfony/messenger` |

## The default templates

`EmailTemplatesRenderer::DEFAULTS` is the directory of the package's default templates, `receipt` and
`donation-receipt`, in English and in French. Add it to the directories `phil/email-templates-bundle` searches; a
directory listed before it replaces a template of the same key and locale:

```yaml
# config/packages/phil_email_templates.yaml
phil_email_templates:
    defaults_paths:
        - '%kernel.project_dir%/email_templates'
        - '%kernel.project_dir%/vendor/phil/receipt-email/email-templates'
```

## Wiring the services

The package is a library: the application declares its services. In a Symfony application:

```yaml
# config/services.yaml
services:
    Phil\ReceiptEmail\Infrastructure\Commerce\CommercePaymentReceipts:
        arguments: ['@Phil\CommerceBundle\Domain\Repository\ReceiptRepository']

    Phil\ReceiptEmail\Infrastructure\Commerce\CommerceReceiptPdf:
        arguments: ['@Phil\CommerceBundle\Domain\Repository\ReceiptRepository']

    Phil\ReceiptEmail\Infrastructure\EmailTemplates\EmailTemplatesRenderer:
        arguments: ['@Phil\EmailTemplatesBundle\Application\Rendering\EmailRenderer']

    Phil\ReceiptEmail\Application\TransactionalCheck: ~

    Phil\ReceiptEmail\Application\ReceiptEmails:
        arguments:
            $payments: '@Phil\ReceiptEmail\Infrastructure\Commerce\CommercePaymentReceipts'
            $donations: '@App\Receipts\DoctrineDonationReceipts'
            $recipients: '@App\Receipts\Recipients'
            $renderer: '@Phil\ReceiptEmail\Infrastructure\EmailTemplates\EmailTemplatesRenderer'
            $from: 'Shop <shop@example.com>'
            $check: '@Phil\ReceiptEmail\Application\TransactionalCheck'
            $paymentPdf: '@Phil\ReceiptEmail\Infrastructure\Commerce\CommerceReceiptPdf'

    Phil\ReceiptEmail\Infrastructure\Messenger\SendPaymentReceiptHandler:
        arguments: ['@Phil\ReceiptEmail\Application\ReceiptEmails', '@mailer.transports']
        tags: [messenger.message_handler]

    Phil\ReceiptEmail\Infrastructure\Messenger\SendDonationReceiptHandler:
        arguments: ['@Phil\ReceiptEmail\Application\ReceiptEmails', '@mailer.transports']
        tags: [messenger.message_handler]
```

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            Phil\ReceiptEmail\Infrastructure\Messenger\SendPaymentReceipt: async
            Phil\ReceiptEmail\Infrastructure\Messenger\SendDonationReceipt: async
```

`App\Receipts\DoctrineDonationReceipts` and `App\Receipts\Recipients` stand for the application's implementations of
`DonationReceiptRepository`, `DonationReceiptNumbering` and `RecipientDirectory`: see
[Extension points](extension-points.md).
