<!-- phil-dev source-sha256=06ac1e222934561b6935f845f554ff2900c7ee608b4bf9b81242b9c6b1ac6afb -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

## Ports

| Port | Implémentations | Implémentez-le pour |
|---|---|---|
| `Application\Port\PaymentReceiptSource` | `Infrastructure\Commerce\CommercePaymentReceipts` | lire les reçus d'un autre système, en construisant chaque `PaymentReceipt` à partir de ses propres montants |
| `Application\Port\RecipientDirectory` | aucune : celle de l'application | donner l'adresse et la locale de l'acheteur d'une commande ou d'un donateur |
| `Application\Port\ReceiptRenderer` | `Infrastructure\EmailTemplates\EmailTemplatesRenderer` | produire le rendu avec un autre moteur de gabarits |
| `Application\Port\PaymentReceiptPdf` | `Infrastructure\Commerce\CommerceReceiptPdf` | imprimer le PDF d'un reçu de paiement |
| `Application\Port\DonationReceiptPdf` | `Infrastructure\Pdf\PagedMediaDonationReceiptPdf` | imprimer le PDF d'un reçu de don |
| `Application\Port\DonationReceiptNumbering` | aucune : celle de l'application | numéroter les reçus de don dans leur série, sur son propre stockage |
| `Domain\Repository\DonationReceiptRepository` | aucune : celle de l'application | garder les reçus de don |

`DonationReceiptNumbering::next()` retourne de 1 à 32 caractères ASCII visibles, uniques dans la série, séquentiels et
sans trous ; il s'exécute dans la transaction qui garde le reçu, et verrouille la série jusqu'à sa fin.

## Options

- `ReceiptEmails` : l'expéditeur, le `TransactionalCheck`, les imprimeurs de PDF.
- `ReceiptMessage::payment()` et `donation()` : la clé de gabarit, `receipt` et `donation-receipt` par défaut.
- `EmailTemplatesRenderer` : le site, le site courant par défaut.
- `CommerceReceiptPdf` : l'imprimeur par défaut et un imprimeur par locale ou par langue.
- `PagedMediaDonationReceiptPdf` : les libellés par locale ou par langue, le format de page, les polices, la
  conformité PDF/A, l'image de la signature.
- Les gabarits par défaut : un gabarit de même clé et de même locale dans un répertoire listé avant
  `EmailTemplatesRenderer::DEFAULTS` remplace celui du paquet.
