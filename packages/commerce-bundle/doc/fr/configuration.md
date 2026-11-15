<!-- phil-dev source-sha256=dc252150621f38c6e0b2627c090e76e74aad70a5c6967165ae00068aefc6b89d -->
# Référence de la configuration

L'alias de l'extension est `phil_commerce`. Ses clés sont la table des taux de taxe, les reçus, les types de documents, les
statuts de commande, l'emballage-cadeau, les paiements hors ligne, les liens de paiement, l'expiration des commandes
impayées et les remboursements.

```yaml
# config/packages/phil_commerce.yaml
phil_commerce:
    tax_rates:
        goods:                       # a tax category, as the sellables name it
            QC: { GST: '5', QST: '9.975' }   # a zone, as the orders name it: rate codes and their percentages
            ON: { HST: '13' }
        digital:
            QC: { GST: '5', QST: '9.975' }
```

| Clé | Type | Valeur par défaut | Effet |
|---|---|---|---|
| `tax_rates` | table : catégorie → zone → code → pourcentage | `{}` | Les taux que `ConfiguredTaxRates` donne à `TaxOrderProcessor` ; une catégorie ou une zone absente n'a aucune taxe |
| `receipts.enabled` | booléen | `false`, `true` une fois le bloc défini | Enregistre `Receipts` |
| `receipts.issuer.name` | chaîne | — | Exigé quand les reçus sont activés |
| `receipts.issuer.address` | liste de chaînes | `[]` | Les lignes de l'adresse de l'émetteur |
| `receipts.issuer.tax_numbers` | table : code de taux → numéro d'inscription | `{}` | Affichés sur chaque reçu |
| `receipts.receipt_prefix`, `receipts.credit_note_prefix` | chaîne | `R-`, `CN-` | Le préfixe de chaque série ; les deux diffèrent, et chacun tient en 32 caractères avec `number_digits` |
| `receipts.number_digits` | entier, de 1 à 18 | `8` | La séquence est complétée de zéros jusqu'à ce nombre de chiffres |
| `document_types` | table : code → `{prefix, digits, visibility}` | `{}` | Les types de documents de l'application dans le registre ; `digits` vaut 8 par défaut, `visibility` vaut `hidden` par défaut, ou `listed`, `unlisted` ([documents.md](documents.md#visibilité)) |
| `order_statuses` | table : statut → `cart`, `quoted`, `placed` ou `cancelled` | `{}` | Les statuts de l'application, chacun rattaché à un état ([lifecycle.md](lifecycle.md#statuts)) |
| `gift_wrapping.enabled` | booléen | `false` | Si la boutique emballe les cadeaux ([gifts.md](gifts.md)) |
| `gift_wrapping.recycled_option` | booléen | `true` | Si elle offre l'emballage recyclé |
| `gift_wrapping.label` | chaîne | `Gift wrapping` | Le libellé de l'ajustement de l'emballage |
| `gift_wrapping.prices`, `gift_wrapping.recycled_prices` | table : devise → décimal | `{}` | Ce que coûte chaque emballage ; gratuit sans prix, et l'emballage recyclé au prix ordinaire sans prix propre |
| `offline_payments` | liste de modes | `[]` | Les modes payés hors ligne et confirmés à la main ([payments.md](payments.md#paiements-hors-ligne)) |
| `payment_links.default_hours`, `payment_links.max_hours` | entier | `72`, `720` | La durée par défaut d'un lien de paiement, et sa durée maximale ([payment-links.md](payment-links.md)) |
| `order_expiry.unpaid_hours`, `order_expiry.delete_after_days` | entier ou `null` | `24`, `30` | Pour les commandes d'aucun canal que la boutique connaît : quand une commande impayée expire, quand une commande expirée est supprimée ([expiry.md](expiry.md)) |
| `refunds.single_step` | booléen | `false` | Si accorder un remboursement l'exécute aussitôt ([refunds.md](refunds.md)) |

- **Les catégories, les zones, les codes de taux et les codes de `tax_numbers` sont gardés tels qu'écrits** :
  `digital-goods`, `CA-QC` et `QC-TVQ` gardent leurs tirets, pour correspondre aux catégories de taxe des articles
  vendables, aux zones de taxe des commandes et aux codes de taux imprimés sur les reçus.
- **Les numéros d'une série tiennent dans leur colonne** : un préfixe et `number_digits` prennent au plus
  32 caractères, la longueur du numéro d'un reçu, et les deux préfixes diffèrent, pour que les deux séries ne donnent
  jamais le même numéro. L'une ou l'autre erreur fait échouer la construction du conteneur.
- **Les pourcentages sont des chaînes décimales**, lues par `TaxRate::ofPercent()` de `phil/tax` : jusqu'à quatre
  décimales, de 0 à 100. Un pourcentage qu'un taux ne peut pas contenir, ou un code qui n'en est pas un, fait échouer
  la construction du conteneur.
- **Un type de document, un prix d'emballage ou un statut que le bundle ne peut pas utiliser fait échouer la
  construction du conteneur** : un code ou un préfixe que deux types partagent, un prix que sa devise ne peut pas
  contenir, un statut d'aucun état, un lien dont la durée par défaut dépasse la durée maximale, un mode hors ligne qui
  n'est pas un nom de mode.
- **Une application avec un moteur de taxes externe** crée plutôt un alias vers son propre `TaxRateResolver` ; la
  table est alors inutilisée ([extension-points.md](extension-points.md)).

## Ce qu'enregistre le bundle

| Service | Rôle |
|---|---|
| `Phil\CommerceBundle\Application\OrderModifier` (public) | Change une commande par les quatre ports étiquetés qu'il lit et par `LineOptions`, puis la recalcule |
| `Phil\CommerceBundle\Application\LineOptions` | Les options de ce qui est vendu, tirées des services étiquetés `phil_commerce.sellable_options` |
| `Phil\CommerceBundle\Application\OptionSupplementProcessor` | Facture ce qu'ajoutent les options ; étiqueté `phil_commerce.order_processor`, avant `TaxOrderProcessor` |
| `Phil\CommerceBundle\Application\OptionDraws` (public) | Tire les options d'une commande payée par les services étiquetés `phil_commerce.option_drawer` |
| `Phil\CommerceBundle\Application\ConfiguredTaxRates` | Les taux de `tax_rates` ; avec l'alias `TaxRateResolver` |
| `Phil\CommerceBundle\Application\TaxOrderProcessor` | Taxe chaque ligne ; étiqueté `phil_commerce.order_processor` sans position, les autres processeurs le nommant dans leur `before` ou leur `after` |
| `Phil\CommerceBundle\Application\Channels` (public) | Avec DoctrineBundle seulement : le canal d'une requête, les commandes qu'ouvre un canal |
| `Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineChannelRepository`, avec l'alias (public) `ChannelRepository` | Avec DoctrineBundle seulement : stocke les canaux |
| `Phil\CommerceBundle\Application\Payments` (public) | Avec DoctrineBundle seulement : les paiements par les passerelles étiquetées `phil_commerce.payment_gateway` |
| `Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineOrderRepository`, avec l'alias (public) `OrderRepository` | Avec DoctrineBundle seulement : stocke les commandes par le gestionnaire d'entités par défaut |
| `Phil\CommerceBundle\Infrastructure\Doctrine\DoctrinePaymentRepository`, avec l'alias (public) `PaymentRepository` | Avec DoctrineBundle seulement : stocke les paiements |
| `Phil\CommerceBundle\Application\DeferredSales` (public) | Avec DoctrineBundle seulement : les ventes différées, par les services étiquetés `phil_commerce.stock_commitments` et par le distributeur d'événements quand il y en a un ([deferred-sales.md](deferred-sales.md)) |
| `Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineDeferredSaleRepository`, avec l'alias (public) `DeferredSaleRepository` | Avec DoctrineBundle seulement : stocke les ventes différées |
| `Phil\CommerceBundle\Application\Receipts` (public) | Avec DoctrineBundle et `receipts` activé : émet les reçus et les notes de crédit |
| `Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineDocumentNumbering`, avec l'alias `DocumentNumbering` et `DocumentSeries` | Avec DoctrineBundle seulement : une rangée verrouillée par série |
| `Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineReceiptRepository`, avec l'alias (public) `ReceiptRepository` | Avec DoctrineBundle seulement : stocke les reçus et les notes de crédit |
| `Phil\CommerceBundle\Application\ManualDiscountProcessor` | Retire les rabais accordés à la main ; étiqueté `phil_commerce.order_processor`, après les options et les promotions du catalogue, avant `TaxOrderProcessor` |
| `Phil\CommerceBundle\Application\GiftWrapping` (public) | L'emballage de `gift_wrapping` ; étiqueté `phil_commerce.order_processor` |
| `phil_commerce.order_workflow` (public) | La machine à états `phil_commerce_order`, sur `OrderMarkingStore` et sur le répartiteur d'événements quand il y en a un ; ajoutée à `workflow.registry` quand il y en a un |
| `Phil\CommerceBundle\Application\OrderLifecycle` (public) | Fait avancer, journalise et force les commandes, fixe leurs statuts, annonce `OrderStateForced` et `OrderCancelled` par le répartiteur d'événements quand il y en a un |
| `Phil\CommerceBundle\Application\DenyForcing`, avec l'alias `TransitionForcing` | Personne ne force une commande tant que l'application n'a pas créé l'alias vers le sien |
| `Phil\CommerceBundle\Application\UnknownShipments`, avec l'alias (public) `OrderShipments` | Aucune commande n'est connue comme partie tant qu'un module de logistique ou l'application n'a pas créé l'alias vers le sien |
| `Phil\CommerceBundle\Application\CoreDocumentTypes` | Les reçus, les notes de crédit et `document_types` ; étiqueté `phil_commerce.document_types` |
| `Phil\CommerceBundle\Application\DocumentRegister` (public) | Le registre des types de documents, numérotés par `DocumentSeries` |
| `Phil\CommerceBundle\Application\WishLists` (public) | Avec DoctrineBundle seulement : les listes de souhaits ; écoute `OrderPurchased` |
| `Phil\CommerceBundle\Application\PaymentLinks` (public) | Avec DoctrineBundle seulement : les liens de paiement |
| `Phil\CommerceBundle\Application\OrderChanges` (public) | Avec DoctrineBundle seulement : les changements des commandes passées |
| `Phil\CommerceBundle\Application\OrderExpiry` (public) | Avec DoctrineBundle seulement : le balayage des commandes impayées |
| `Phil\CommerceBundle\Application\Refunds` (public) | Avec DoctrineBundle seulement : les remboursements en deux temps, annoncés par `OrderRefunded` par le répartiteur d'événements quand il y en a un |
| `DoctrineWishListRepository`, `DoctrinePaymentLinkRepository`, `DoctrineOrderChangeRepository`, `DoctrineOrderJournal`, `DoctrineRefundRepository`, chacun avec l'alias (public) de son port | Avec DoctrineBundle seulement : stockent les listes de souhaits, les liens de paiement, les changements, le journal et les remboursements |
| `Phil\CommerceBundle\Infrastructure\Http\PaymentWebhookController` (public) | Avec DoctrineBundle seulement : reçoit les notifications des processeurs à la route que lui donne l'application |

`Payments` et `Receipts` ont besoin des dépôts et de la numérotation que fournit Doctrine : une application qui
stocke autrement les paiements et les reçus déclare elle-même les deux services, avec ses propres adaptateurs de
`PaymentRepository`, de `ReceiptRepository` et de `DocumentNumbering`.

Avec DoctrineBundle, le bundle ajoute aussi en amont les types de colonnes `phil_commerce_order_lines` et
`phil_commerce_adjustments`, le mappage par attributs de `Domain\Model` ([orders.md](orders.md#stockage)) et celui
des séquences de documents.

L'autoconfiguration étiquette chaque service qui implémente `SellableResolver`, `UnitPriceResolver`, `OrderProcessor`,
`PaymentGateway`, `SellableAvailability`, `SellableOptions`, `OptionDrawer`, `StockCommitments` ou `DocumentTypes` ;
les résolveurs de prix unitaire et les processeurs de commande prennent leur place avec `before` et `after` sur leur
étiquette, qui nomment les services devant lesquels ou après lesquels ils se placent
([extension-points.md](extension-points.md)).

## Ce que l'application décide et câble elle-même

| Décision | Où elle va |
|---|---|
| Les modules avec lesquels elle vend | Les paquets de modules qu'elle installe et enregistre ([modules.md](modules.md)) |
| Les devises qu'elle accepte | `phil_money.currencies`, avec `phil/money-bundle` ([money.md](money.md)) |
| Ce qu'elle vend d'autre | Un `SellableResolver` à elle ([extension-points.md](extension-points.md)) |
| Les options de ce qu'elle vend, et qui les tire | Un `SellableOptions` et ses `OptionDrawer`, sauf si un module les déclare ([options.md](options.md)) |
| Quand les tirages d'une commande payée sont faits | Un appel à `OptionDraws::draw()` une fois ses paiements `Paid` ([options.md](options.md#les-tirages-après-le-paiement)) |
| Les taux de taxe d'une vente | `phil_commerce.tax_rates`, ou son propre `TaxRateResolver` ([taxes.md](taxes.md)) |
| Le budget, le pas de pénalité et la fenêtre du limiteur | `PaymentAttemptPolicy::of()`, à partir de paramètres ([payment-attempts.md](payment-attempts.md)) |
| Le préfixe IPv6 qui forme un client | Le second argument de `PaymentAttemptKey::forClientIp()`, 64 par défaut |
| Qui émet ses reçus, et comment se lisent leurs numéros | `phil_commerce.receipts` ([receipts.md](receipts.md)) |
| Les processeurs qu'elle utilise | Une `PaymentGateway` par processeur, et la route du point de terminaison de *webhook* ([payments.md](payments.md)) |
| Où sont stockés les états du limiteur | Un adaptateur de `PaymentAttemptStateStore` |
| Qui peut forcer une commande hors de ses transitions | Son propre `TransitionForcing`, en alias par-dessus `DenyForcing` ([lifecycle.md](lifecycle.md#forcer-une-transition)) |
| Qui peut accorder un rabais, accorder ou exécuter un remboursement | Ses voters de sécurité, avant d'appeler `OrderModifier` ou `Refunds` |
| Quand les commandes impayées expirent | `OrderExpiry::sweep()` selon un horaire, et les délais de chaque canal ([expiry.md](expiry.md)) |
| Qui apprend qu'une commande est payée, remboursée ou annulée | Les écouteurs de `OrderPurchased`, `OrderRefunded` et `OrderCancelled` ([extension-points.md](extension-points.md#événements)) |
| Quand une commande a quitté la boutique | Son propre `OrderShipments`, ou celui d'un module de logistique, en alias par-dessus `UnknownShipments` ([extension-points.md](extension-points.md#ordershipments)) |
| Qui compte les verdicts de robot vus au paiement | Le `VerdictHandler` de `phil/bot-detection-bundle`, avec un compteur câblé pour le paiement ([payment-attempts.md](payment-attempts.md)) |
| Si un verdict de robot impose un défi d'authentification plus fort ou ajoute une pénalité | Son parcours de paiement |
