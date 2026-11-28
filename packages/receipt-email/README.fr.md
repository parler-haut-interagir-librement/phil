<!-- phil-dev source-sha256=fdc0cdc1274e2f4c3f7d48ed5cb6ab19fd1eb144a3af37eb8c7a486f4f566d59 -->
# phil/receipt-email

[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/parler-haut-interagir-librement/receipt-email/badge)](https://scorecard.dev/viewer/?uri=github.com/parler-haut-interagir-librement/receipt-email)

**Envoyez à chaque paiement son reçu numéroté et à chaque don son reçu fiscal officiel : les montants tels qu'émis, une
partie en texte brut, le PDF sur demande, aucun pistage.**

phil/receipt-email est une bibliothèque PHP simple, bâtie sur `phil/money` et Symfony Mime. Elle construit le courriel
d'un **reçu de paiement** ou d'une **note de crédit** à partir du reçu que son émetteur a numéroté — les lignes, les
taxes de chaque ligne, les autres frais, les taxes totalisées par code de taux, le sous-total et le total, chaque
montant étant un `Money` copié tel qu'émis et jamais recalculé —, ainsi que le courriel d'un **reçu officiel de don**,
un document à part avec sa propre série de numéros de série, les mentions que l'Agence du revenu du Canada demande à un
reçu officiel, et l'avantage reçu par le donateur retranché du montant admissible.

Un reçu reste un **message transactionnel**. Le noyau transforme un reçu en **`ReceiptMessage`** — une clé de gabarit,
une locale et des variables typées —, un port **`ReceiptRenderer`** en produit le rendu, et **`TransactionalCheck`**
refuse le résultat avant l'envoi s'il n'a pas de partie en texte brut à côté de son HTML, s'il charge quoi que ce soit —
chaque image est intégrée et affichée en `cid:`, sans pixel espion, sans script, cadre ni feuille de style distante —,
ou s'il contient un lien marqué pour le pistage. Les variables ne portent que le reçu et le nom du destinataire :
aucune offre glissée. **`ReceiptEmails`** construit l'`Email` de Symfony Mime, marqué `Auto-Submitted: auto-generated`,
avec le PDF en pièce jointe seulement quand l'application le demande.

Les adaptateurs sont facultatifs : **`CommercePaymentReceipts`** et **`CommerceReceiptPdf`** lisent et impriment les
reçus de `phil/commerce-bundle` ; **`EmailTemplatesRenderer`** produit le rendu avec `phil/email-templates-bundle` —
gabarits par site et par locale, Twig en bac à sable —, à partir des gabarits par défaut `receipt` et
`donation-receipt` que le paquet fournit en anglais et en français ; **`PagedMediaDonationReceiptPdf`** imprime un reçu
de don avec `phil/paged-media`. Deux messages de Messenger, **`SendPaymentReceipt`** et **`SendDonationReceipt`**,
portent le numéro d'un reçu et rien d'autre ; leurs *handlers* relisent le reçu et son destinataire dans le *worker* et
envoient par un transport de Mailer. Ses services ne gardent aucun état entre les requêtes et conviennent à un *worker*
de longue durée.

## État

| Partie | État |
|---|---|
| `PaymentReceipt`, `ReceiptLine`, `ReceiptCharge`, `TaxAmount`, `Party`, `PaymentDocument` : un reçu tel qu'émis, vérifié | Implémenté et testé |
| `Donation`, `DonationReceipt`, `GiftKind`, `DonationReceipts`, `DonationReceiptNumbering`, `DonationReceiptRepository` : les reçus officiels de don dans leur propre série | Implémenté et testé |
| `ReceiptMessage`, `ReceiptRenderer`, `RenderedReceipt`, `TransactionalCheck`, `ReceiptEmails` : le message, son rendu, ses vérifications, l'`Email` | Implémenté et testé |
| `CommercePaymentReceipts`, `CommerceReceiptPdf` : les reçus de `phil/commerce-bundle` | Implémenté et testé |
| `EmailTemplatesRenderer` et les gabarits par défaut `receipt` et `donation-receipt`, en anglais et en français | Implémenté et testé |
| `PagedMediaDonationReceiptPdf` : le PDF d'un reçu de don | Implémenté et testé |
| `SendPaymentReceipt`, `SendDonationReceipt` et leurs *handlers* : Messenger avec des identifiants seulement | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, les paquets facultatifs, le câblage des services. |
| [Reçus de paiement](doc/fr/payment-receipts.md) | Ce que contient le courriel d'un reçu de paiement, les notes de crédit, les taxes, les adaptateurs de commerce, le PDF. |
| [Reçus de don](doc/fr/donation-receipts.md) | Le reçu officiel de don, ses mentions telles que l'Agence du revenu du Canada les énumère, l'avantage, la série. |
| [Gabarits et rendu](doc/fr/templates.md) | Le message et ses variables, les gabarits par défaut, `phil/email-templates-bundle`. |
| [Envoi](doc/fr/sending.md) | `ReceiptEmails`, les vérifications transactionnelles, Messenger et le transport de Mailer. |
| [Sécurité](doc/fr/security.md) | Ce dont le paquet protège, et ce qui revient à l'application. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports à implémenter, les options, et quand ils sont stables. |
| [Tests](doc/fr/testing.md) | Les tests du paquet. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

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

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-dom`, `ext-libxml` et `ext-mbstring`, Symfony Mime 8.2 ou une version
plus récente, et `phil/money`. `phil/commerce-bundle`, `phil/email-templates-bundle`, `phil/paged-media`, Symfony Mailer
et Symfony Messenger sont facultatifs, chacun pour son adaptateur.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
