<!-- phil-dev source-sha256=cbdd254e9bb1a6a409d5f2adf79956f9f066b982bebe72485260f1e3ade001bc -->
# Référence

Chaque type public du paquet, avec son rôle.

## Domaine

| Type | Rôle |
|---|---|
| `Domain\ValueObject\PaymentReceipt` | Un reçu de paiement ou une note de crédit tel qu'émis : numéros, émetteur, lignes, frais, taxes, sous-total, total. |
| `Domain\ValueObject\ReceiptLine` | Une ligne : description, quantité, prix unitaire, montant, ses taxes, ses options montrées et en attente. |
| `Domain\ValueObject\ReceiptCharge` | Un montant autre que les lignes et les taxes, avec son libellé. |
| `Domain\ValueObject\TaxAmount` | Une taxe par code de taux, son montant, et l'indication que les prix l'incluaient. |
| `Domain\ValueObject\Party` | Un nom et les lignes d'une adresse. |
| `Domain\Payment\PaymentDocument` | `Receipt` ou `CreditNote`. |
| `Domain\ValueObject\Donation` | Un don tel qu'un reçu officiel de don l'énonce ; `eligibleAmount()`. |
| `Domain\ValueObject\DonationReceipt` | Un don avec son numéro de série et sa date d'émission ; le nom et le site Web de l'autorité fiscale. |
| `Domain\Donation\GiftKind` | `Cash` ou `NonCash`. |
| `Domain\Repository\DonationReceiptRepository` | Garde les reçus de don. |
| `Domain\Exception\ReceiptEmailException` | Toute exception du paquet. |
| `Domain\Exception\InvalidReceipt` | Une valeur refusée, qui nomme la règle, jamais les renseignements personnels. |
| `Domain\Exception\ReceiptNotFound` | Un reçu ou un destinataire introuvable quand le courriel est construit. |
| `Domain\Exception\NotTransactional` | Un reçu produit que `TransactionalCheck` refuse. |
| `Domain\Exception\PdfUnavailable` | Un PDF demandé sans imprimeur. |

## Application

| Type | Rôle |
|---|---|
| `Application\ReceiptEmails` | Construit l'`Email` d'un reçu de paiement ou de don à partir de son numéro. |
| `Application\ReceiptMessage` | La clé de gabarit, la locale et les variables du courriel d'un reçu. |
| `Application\RenderedReceipt` | Le sujet, le préentête, le HTML, le texte brut et les images produits. |
| `Application\InlineImage` | Une image jointe en ligne, affichée en `cid:<name>`. |
| `Application\Recipient` | L'adresse et la locale de qui reçoit un reçu. |
| `Application\TransactionalCheck` | Refuse un reçu produit sans texte brut, avec du contenu distant ou des liens pistés. |
| `Application\DonationReceipts` | Émet les reçus de don dans leur propre série. |
| `Application\Port\PaymentReceiptSource` | Lit un reçu de paiement par son numéro. |
| `Application\Port\RecipientDirectory` | Donne le destinataire d'un reçu. |
| `Application\Port\ReceiptRenderer` | Produit le rendu d'un `ReceiptMessage`. |
| `Application\Port\PaymentReceiptPdf` | Imprime un reçu de paiement. |
| `Application\Port\DonationReceiptPdf` | Imprime un reçu de don. |
| `Application\Port\DonationReceiptNumbering` | Donne le prochain numéro de série des reçus de don. |

## Infrastructure

| Type | Rôle |
|---|---|
| `Infrastructure\Commerce\CommercePaymentReceipts` | `PaymentReceiptSource` sur les reçus de `phil/commerce-bundle`. |
| `Infrastructure\Commerce\CommerceReceiptPdf` | `PaymentReceiptPdf` avec le `ReceiptPdf` de `phil/commerce-bundle`. |
| `Infrastructure\EmailTemplates\EmailTemplatesRenderer` | `ReceiptRenderer` avec `phil/email-templates-bundle` ; `DEFAULTS`, le répertoire des gabarits par défaut. |
| `Infrastructure\Pdf\PagedMediaDonationReceiptPdf` | `DonationReceiptPdf` avec `phil/paged-media`. |
| `Infrastructure\Messenger\SendPaymentReceipt` | Le message qui demande le courriel d'un reçu de paiement, par numéro. |
| `Infrastructure\Messenger\SendDonationReceipt` | Le message qui demande le courriel d'un reçu de don, par numéro de série. |
| `Infrastructure\Messenger\SendPaymentReceiptHandler` | Construit et envoie le courriel d'un reçu de paiement par un transport de Mailer. |
| `Infrastructure\Messenger\SendDonationReceiptHandler` | Construit et envoie le courriel d'un reçu de don par un transport de Mailer. |
