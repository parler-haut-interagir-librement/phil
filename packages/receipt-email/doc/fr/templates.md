<!-- phil-dev source-sha256=e96b42c2443b754dfe20fabc674ea2cf8a7bf25255de87a3a44d4c930719ced2 -->
# Gabarits et rendu

## Le message

`ReceiptMessage::payment()` et `ReceiptMessage::donation()` transforment un reçu et son destinataire en ce que dit le
courriel : une clé de gabarit — `receipt` ou `donation-receipt`, ou une clé que l'application nomme —, la locale du
destinataire, et les variables. Les variables sont les valeurs propres du reçu : chaque montant écrit comme une décimale
de sa devise par `Money::toDecimal()` (`56.74`, `-12.50` sur une note de crédit), les dates en `DateTimeImmutable`, le
nom du destinataire ou `null`. Aucune variable ne porte une offre, un identifiant de pistage ni rien d'autre que le
reçu.

### Un reçu de paiement

| Variable | Contient |
|---|---|
| `recipient.name` | Le nom à saluer, ou `null`. |
| `receipt.number`, `receipt.document`, `receipt.order`, `receipt.credits` | Le numéro, `receipt` ou `credit_note`, la commande, le reçu crédité ou `null`. |
| `receipt.issued_at`, `receipt.currency` | La date d'émission, le code de la devise. |
| `receipt.subtotal`, `receipt.total`, `receipt.included_total` | Le sous-total, le total, les taxes que les prix incluaient ou `null`. |
| `issuer.name`, `issuer.address`, `issuer.tax_numbers` | L'émetteur, les lignes de son adresse, ses numéros d'inscription sous la forme `GST 123456789 RT0001`. |
| `lines[].description`, `quantity`, `unit_price`, `amount` | Chaque ligne. |
| `lines[].taxes`, `lines[].options`, `lines[].pending` | Les taxes de la ligne sous la forme `GST 0.75`, ses options sous la forme `Colour: Blue`, les libellés de ses options qui restent à tirer. |
| `charges[].label`, `charges[].amount` | Livraison, frais, rabais, pourboires. |
| `taxes[].code`, `taxes[].amount` | Chaque taxe ajoutée au total, par code de taux. |
| `included_taxes[].code`, `included_taxes[].amount` | Chaque taxe que les prix incluaient. |

Les taxes et les options d'une ligne sont des listes de textes plutôt que d'objets : `phil/email-templates-bundle`
imbrique un schéma sur trois niveaux au plus.

### Un reçu de don

| Variable | Contient |
|---|---|
| `recipient.name` | Le nom à saluer, ou `null`. |
| `receipt.serial_number`, `receipt.issued_at`, `receipt.issued_in` | Le numéro de série, la date et le lieu d'émission. |
| `receipt.received_on`, `receipt.received_year` | La date où le don a été reçu ou `null`, et son année. |
| `receipt.kind` | `cash` ou `non_cash`. |
| `receipt.currency`, `receipt.amount`, `receipt.advantage`, `receipt.eligible_amount` | Les montants. |
| `receipt.advantage_description`, `receipt.gift_description`, `receipt.signatory` | Les descriptions, ou `null`, et le signataire. |
| `charity.name`, `charity.address`, `charity.registration_number` | L'organisme de bienfaisance. |
| `donor.name`, `donor.address` | Le donateur. |
| `appraiser.name`, `appraiser.address` | L'évaluateur d'un don de biens, ou `appraiser` vaut `null`. |
| `authority.name`, `authority.website` | `Canada Revenue Agency` ou `Agence du revenu du Canada` selon la locale, et `canada.ca/charities-giving`. |

## Le moteur de rendu

Le port `ReceiptRenderer` produit le rendu d'un message en un `RenderedReceipt` : le sujet, le préentête, le HTML, le
texte brut et les images intégrées sous forme d'`InlineImage`. `Infrastructure\EmailTemplates\EmailTemplatesRenderer`
produit le rendu avec l'`EmailRenderer` de `phil/email-templates-bundle` : le gabarit publié du site pour la clé et la
locale, sinon le gabarit partagé, sinon le fichier par défaut ; la locale se replie sur sa langue, puis sur la locale
par défaut. Le site est le site courant, ou celui que reçoit son constructeur. Les valeurs sont vérifiées selon le
schéma du gabarit, et le gabarit produit son rendu dans le bac à sable Twig du bundle.

## Les gabarits par défaut

Le répertoire `email-templates/` contient `receipt.en.yaml`, `receipt.fr.yaml`, `donation-receipt.en.yaml` et
`donation-receipt.fr.yaml`. Chacun déclare le schéma des variables ci-dessus avec des valeurs d'exemple, écrit son corps
HTML avec des styles en ligne et sa propre partie en texte brut, n'intègre aucune image et ne mène nulle part. Les
gabarits français écrivent les montants avec une virgule décimale. Un site les modifie comme il modifie n'importe quel
gabarit de `phil/email-templates-bundle` ; ses gabarits passent tout de même par `TransactionalCheck`.

La mise en page maison de `phil/email-templates-bundle` affiche `brand.logo` comme une image : un logo donné sous forme
d'adresse est refusé par `TransactionalCheck`, comme toute image distante. Omettez-le, ou donnez `cid:logo` et déclarez
l'image dans les `embeds` des gabarits, qui la joignent en ligne.
