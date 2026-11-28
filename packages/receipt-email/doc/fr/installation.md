<!-- phil-dev source-sha256=d46832bf50d0e60c3152ce775d1374787a7be7a92a5c2689a3f2d5523135a41b -->
# Installation

```sh
composer require phil/receipt-email
```

Le paquet exige PHP 8.5 ou une version plus récente avec `ext-dom`, `ext-libxml` et `ext-mbstring`, Symfony Mime 8.2 ou
une version plus récente, et `phil/money`. Chaque adaptateur exige le paquet qu'il adapte :

| Adaptateur | Installation |
|---|---|
| `Infrastructure\Commerce\CommercePaymentReceipts`, `CommerceReceiptPdf` | `phil/commerce-bundle` |
| `Infrastructure\EmailTemplates\EmailTemplatesRenderer` | `phil/email-templates-bundle` |
| `Infrastructure\Pdf\PagedMediaDonationReceiptPdf` | `phil/paged-media` |
| `Infrastructure\Messenger\SendPaymentReceiptHandler`, `SendDonationReceiptHandler` | `symfony/mailer`, `symfony/messenger` |

## Les gabarits par défaut

`EmailTemplatesRenderer::DEFAULTS` est le répertoire des gabarits par défaut du paquet, `receipt` et
`donation-receipt`, en anglais et en français. Ajoutez-le aux répertoires que `phil/email-templates-bundle` parcourt ;
un répertoire listé avant lui remplace un gabarit de même clé et de même locale :

```yaml
# config/packages/phil_email_templates.yaml
phil_email_templates:
    defaults_paths:
        - '%kernel.project_dir%/email_templates'
        - '%kernel.project_dir%/vendor/phil/receipt-email/email-templates'
```

## Câbler les services

Le paquet est une bibliothèque : l'application déclare ses services. Dans une application Symfony :

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

`App\Receipts\DoctrineDonationReceipts` et `App\Receipts\Recipients` désignent les implémentations, propres à
l'application, de `DonationReceiptRepository`, de `DonationReceiptNumbering` et de `RecipientDirectory` : voyez
[Points d'extension](extension-points.md).
