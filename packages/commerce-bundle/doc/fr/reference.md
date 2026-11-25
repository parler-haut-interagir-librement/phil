<!-- phil-dev source-sha256=bf94062d119f9c515ef319f035dd888976069a9a05adedf81e7568c84f15f7cd -->
# Référence

Chaque type public du bundle, par couche, et les parties de la conception qui ne sont pas encore commencées. Les
montants et les devises sont le `Money` et la `Currency` de `phil/money` ([money.md](money.md)) ; les taux, les
lignes, les ventilations et leur calculateur sont les types de `phil/tax` ([taxes.md](taxes.md)).

## `Phil\CommerceBundle\Domain`

| Type | Nature | Rôle | État |
|---|---|---|---|
| `Model\Channel` | `final class`, entité Doctrine | Un site ou un point de vente : les domaines, la devise, la locale, le thème, la zone de taxe | Implémenté et testé |
| `Repository\ChannelRepository` | interface | Trouve et stocke les canaux | Implémenté par `DoctrineChannelRepository` |
| `Exception\UnknownChannel`, `Exception\NotSoldThere` | `final class`, `DomainError` | Aucun canal ouvert pour l'hôte ou le code ; un article vendable hors de la sélection du canal | Implémenté et testé |
| `Model\Order` | `final class`, entité Doctrine | Les lignes, les ajustements, la zone de taxe, le canal, le groupe de clients, les codes de promotion, l'état, la version ; les totaux calculés | Implémenté et testé |
| `Order\OrderState` | énumération adossée à une chaîne | `Cart`, `Quoted`, `Placed`, `Cancelled` | Implémenté et testé |
| `Model\Quote`, `Order\QuoteState` | `final class`, entité Doctrine ; énumération | Une soumission, `Pending`, `Accepted`, `Declined` | Implémenté et testé |
| `Exception\QuoteRefused` | `final class`, `DomainError` | Une soumission inconnue, répondue ou expirée ; une commande dont le reçu est émis | Implémenté et testé |
| `Repository\QuoteRepository` | interface | Les soumissions | Implémenté par `DoctrineQuoteRepository` |
| `Repository\OrderRepository` | interface | Trouve et stocke les commandes | Implémenté par `DoctrineOrderRepository` |
| `Exception\OrderLocked` | `final class`, `DomainError` | Un changement ou un passage que refuse l'état de la commande | Implémenté et testé |
| `Model\DeferredSale` | `final class`, entité Doctrine | Une commande passée payée plus tard : dépôt, déclencheur, engagement du stock, livraison, échéance, état | Implémenté et testé |
| `ValueObject\DeferredSalePolicy`, `ValueObject\Deposit` | `final readonly class` | Comment une vente est payée plus tard ; ce qui est demandé d'abord | Implémenté et testé |
| `Deferred\BalanceTrigger`, `Deferred\StockCommitment`, `Deferred\Fulfilment`, `Deferred\DeferredSaleState` | énumérations adossées à une chaîne | `Date`, `Shipment`, `Availability`, `Manual` ; `OnOrder`, `OnBalance` ; `OnDeposit`, `OnBalance` ; les cinq états | Implémenté et testé |
| `Event\DeferredSaleEvent` | `abstract readonly class`, `DomainEvent` | `DeferredSaleOpened`, `DepositReceived`, `BalanceCalled`, `DeferredSaleSettled`, `DeferredSaleCancelled`, `StockCommitted`, `StockReleased` | Implémenté et testé |
| `Contract\DeferredSaleRepository` | interface | Les ventes différées et les soldes échus | Implémenté par `DoctrineDeferredSaleRepository` |
| `Exception\DeferredSaleRefused` | `final class`, `DomainError` | Une vente différée qui ne peut être ouverte, appelée ou annulée | Implémenté et testé |
| `Model\Payment` | `final class`, entité Doctrine | Un paiement : le montant, le remboursé, l'état, la référence du processeur, les notifications appliquées | Implémenté et testé |
| `Payment\PaymentState` | énumération adossée à une chaîne | Les neuf états d'un paiement | Implémenté et testé |
| `Payment\PaymentEvent` | énumération adossée à une chaîne | Ce que dit une notification | Implémenté et testé |
| `Payment\OrderPaymentState` | énumération adossée à une chaîne | Non payée, partiellement payée, payée, partiellement remboursée, remboursée | Implémenté et testé |
| `ValueObject\PaymentNotification` | `final readonly class` | Une notification vérifiée | Implémenté et testé |
| `ValueObject\PaymentStart` | `final readonly class` | La référence du processeur, et une page ou un secret client | Implémenté et testé |
| `ValueObject\OrderPayments` | `final readonly class` | Les paiements d'une commande additionnés | Implémenté et testé |
| `Repository\PaymentRepository` | interface | Trouve et stocke les paiements | Implémenté par `DoctrinePaymentRepository` |
| `Exception\PaymentRefused` | `final class`, `DomainError` | Un paiement qui ne peut pas être commencé, remboursé ou avancé | Implémenté et testé |
| `Model\Receipt` | `final class`, entité Doctrine | Un reçu ou une note de crédit, jamais modifié | Implémenté et testé |
| `Receipt\DocumentKind` | énumération adossée à une chaîne | `Receipt`, `CreditNote` | Implémenté et testé |
| `ValueObject\Issuer` | `final readonly class` | Le nom, l'adresse, les numéros d'inscription | Implémenté et testé |
| `Repository\ReceiptRepository` | interface | Trouve et stocke les reçus et les notes de crédit | Implémenté par `DoctrineReceiptRepository` |
| `Exception\ReceiptRefused` | `final class`, `DomainError` | Un reçu ou une note de crédit qui ne peut pas être émis | Implémenté et testé |
| `Exception\InvalidNotification` | `final class`, `DomainError` | Une notification à laquelle sa passerelle ne peut pas se fier ou qu'elle ne peut pas lire | Implémenté et testé |
| `ValueObject\OrderLine` | `final readonly class` | Ce qui est vendu, la quantité, le prix unitaire, la catégorie de taxe, la configuration ; le sous-total | Implémenté et testé |
| `ValueObject\SellableReference` | `final readonly class` | Le type et l'identifiant de ce que vend une ligne, et l'empreinte d'une ligne configurée | Implémenté et testé |
| `ValueObject\Sellable` | `final readonly class` | La description, le prix unitaire de base, la catégorie de taxe, les options | Implémenté et testé |
| `Channel\Visibility` | `enum` | Jusqu'où un canal montre un article vendable : `Listed`, `Unlisted` (lien direct seulement), `Hidden` ; `isListed()`, `isReachable()`, `stricter()` | Implémenté et testé |
| `Channel\Offer` | `final readonly class` | Ce qu'un canal offre d'un article vendable : `visibility`, `orderable`, `priceShown` ; `of()`, `full()`, `hidden()`, `stricter()`, `isListed()`, `isReachable()` | Implémenté et testé |
| `ValueObject\OptionSet` | `final readonly class` | Les options d'un article vendable ; vérifie une configuration, donne ses suppléments | Implémenté et testé |
| `ValueObject\OptionDefinition` | `final readonly class` | Une option : texte, choix, encre, tirage ou montant | Implémenté et testé |
| `ValueObject\AmountRange` | `final readonly class` | Les montants qu'un acheteur peut choisir : `minimum`, `maximum` ; `read()`, `refusal()` | Implémenté et testé |
| `ValueObject\OptionChoice` | `final readonly class` | Un choix ou une encre : code, libellé, image, supplément | Implémenté et testé |
| `ValueObject\TextConstraints`, `ValueObject\PrintZone` | `final readonly class` | La longueur, les caractères, la police, la zone, les sources et les entrées d'un texte ; une zone en millimètres | Implémenté et testé |
| `Option\OptionKind`, `Option\TextSource` | énumérations adossées à une chaîne | `Text`, `Choice`, `Ink`, `Draw`, `Amount` ; `Free`, `List`, `Drawn` | Implémenté et testé |
| `ValueObject\LineConfiguration`, `ValueObject\OptionValue` | `final readonly class` | Les valeurs d'options d'une ligne, leur empreinte et leurs tirages ; une valeur, choisie ou à tirer | Implémenté et testé |
| `Exception\InvalidConfiguration`, `Exception\DrawRefused` | `final class`, `DomainError` | Une configuration que les options refusent ; des tirages refusés avant le paiement ou sans tireur | Implémenté et testé |
| `ValueObject\PricingContext` | `final readonly class` | La quantité, le moment, le groupe de clients, le canal | Implémenté et testé |
| `ValueObject\Adjustment` | `final readonly class` | Le type, le libellé, le montant signé, la ligne, neutre, l'origine | Implémenté et testé |
| `Adjustment\AdjustmentType` | énumération adossée à une chaîne | `Option`, `Promotion`, `Proration`, `Tax`, `Shipping`, `Fee`, `Tip` | Implémenté et testé |
| `Exception\LineNotInOrder` | `final class`, `DomainError` | Un changement ou un ajustement nomme une ligne absente de la commande | Implémenté et testé |
| `Exception\UnknownSellable` | `final class`, `DomainError` | Aucun article vendable n'est connu sous une référence | Implémenté et testé |
| `ValueObject\PaymentAttemptPolicy` | `final readonly class` | Le budget, le pas de pénalité, la fenêtre | Implémenté et testé |
| `ValueObject\PaymentAttemptKey` | `final readonly class` | La clé de commande, ou la clé de réseau du client | Implémenté et testé |
| `ValueObject\PaymentAttemptState` | `final readonly class` | Les tentatives, l'attente cumulée, la dernière tentative | Implémenté et testé |
| `ValueObject\PaymentAttemptDecision` | `final readonly class` | Permise, ou nouvelle tentative à une heure donnée ; `Retry-After` ; la plus restrictive | Implémenté et testé |
| `Service\PaymentAttemptLimiter` | `final readonly class` | `decide()`, `record()`, `timeToLiveSeconds()` | Implémenté et testé |
| `Model\WishList`, `ValueObject\WishListItem` | entité Doctrine, objet-valeur | Une liste de souhaits partagée par un lien, ce qu'elle souhaite et ce qui a été acheté | Implémenté et testé |
| `Model\PaymentLink` | entité Doctrine | Un lien qui ouvre une commande passée jusqu'à son expiration | Implémenté et testé |
| `Model\OrderChange`, `Change\OrderChangeState`, `ValueObject\OrderChangeLine`, `Exception\OrderChangeRefused` | entité Doctrine, énumération, objet-valeur, `DomainError` | Un changement d'une commande passée, préparé, confirmé ou abandonné | Implémenté et testé |
| `Model\OrderStateChange` | entité Doctrine en lecture seule | Un passage d'une commande, journalisé | Implémenté et testé |
| `Model\Refund`, `Refund\RefundState` | entité Doctrine, énumération | Un remboursement accordé, exécuté ou retiré | Implémenté et testé |
| `ValueObject\GiftOptions` | `final readonly class` | L'emballage, l'emballage recyclé, le message, les prix cachés | Implémenté et testé |
| `ValueObject\ManualDiscount` | `final readonly class` | Un rabais accordé à la main, montant ou part, sur une ligne ou sur la commande | Implémenté et testé |
| `ValueObject\DocumentType` | `final readonly class` | Un type du registre : code, préfixe, chiffres, visibilité | Implémenté et testé |
| `ValueObject\Identifier` | `final readonly class` | Vérifie un identifiant stable, jamais une adresse de courriel | Implémenté et testé |
| `ValueObject\ExpirySweep` | `final readonly class` | Ce qu'un balayage a fait expirer et supprimé | Implémenté et testé |
| `Event\OrderPurchased`, `Event\OrderRefunded`, `Event\OrderCancelled`, `Event\OrderStateForced` | `DomainEvent` | Une commande payée en entier ; un remboursement exécuté ; une commande annulée, expirée ou forcée vers annulée ; une commande forcée | Implémenté et testé |
| `Contract\OrderJournal`, `Contract\WishListRepository`, `Contract\PaymentLinkRepository`, `Contract\OrderChangeRepository`, `Contract\RefundRepository`, `Contract\ExpiringOrders` | contrats | Le stockage des enregistrements ci-dessus | Implémenté par leurs adaptateurs Doctrine |

## `Phil\CommerceBundle\Application`

| Type | Nature | Rôle | État |
|---|---|---|---|
| `OrderWorkflow`, `OrderMarkingStore` | `final class`, magasin de marquage | La machine à états `phil_commerce_order` et ses places | Implémenté et testé |
| `OrderLifecycle` | `final readonly class` | Fait avancer, journalise et force les commandes, fixe leurs statuts | Implémenté et testé |
| `DenyForcing`, `Contract\TransitionForcing` | `final readonly class`, port | Qui peut forcer une commande ; personne par défaut | Implémenté et testé |
| `UnknownShipments`, `Contract\OrderShipments` | `final readonly class`, port | Quand une commande ou une ligne a quitté la boutique ; inconnu par défaut | Implémenté et testé |
| `GiftWrapping` | `final readonly class`, `OrderProcessor` | L'emballage qu'offre une boutique, facturé une fois par commande emballée | Implémenté et testé |
| `ManualDiscountProcessor` | `final readonly class`, `OrderProcessor` | Les rabais accordés à la main, avant les taxes | Implémenté et testé |
| `WishLists` | `final readonly class` | Les listes de souhaits, partagées par un lien, où l'on achète, comptées sur `OrderPurchased` | Implémenté et testé |
| `PaymentLinks` | `final readonly class` | Les liens qui ouvrent une commande passée jusqu'à leur expiration | Implémenté et testé |
| `OrderChanges` | `final readonly class` | Les changements des commandes passées, préparés, confirmés et versionnés | Implémenté et testé |
| `OrderExpiry` | `final readonly class` | Fait expirer les commandes impayées et supprime les commandes expirées, par canal | Implémenté et testé |
| `Refunds` | `final readonly class` | Les remboursements accordés puis exécutés | Implémenté et testé |
| `Contract\PaymentLock` | port | Tient un paiement pour un seul remboursement à la fois, dans la transaction | Implémenté par `DoctrinePaymentLock` |
| `DocumentRegister`, `CoreDocumentTypes`, `Contract\DocumentTypes`, `Contract\DocumentSeries` | `final class`, `final readonly class`, ports | Le registre des types de documents, leurs séries et leur visibilité ; `types()`, `listed()`, `type()`, `next()` | Implémenté et testé |
| `SecretToken` | `final class` | Le secret d'un lien, montré une fois, stocké sous forme de SHA-256 | Implémenté et testé |
| `OrderModifier` | `final readonly class` | Change une commande par les ports et la recalcule | Implémenté et testé |
| `SellableOffers` | `final readonly class` | Ce qu'un canal offre d'un article vendable, la réponse la plus stricte de chaque sélection | Implémenté et testé |
| `TaxOrderProcessor` | `final readonly class`, `OrderProcessor` | Taxe chaque ligne avec ses options, après ses promotions | Implémenté et testé |
| `LineOptions` | `final readonly class` | Les options d'une référence, tirées du `SellableOptions` de son type | Implémenté et testé |
| `OptionSupplementProcessor` | `final readonly class`, `OrderProcessor` | Facture ce qu'ajoutent les options de chaque ligne | Implémenté et testé |
| `OptionDraws` | `final readonly class` | Tire les options d'une commande payée | Implémenté et testé |
| `Port\SellableOptions` | port | Les options de ce qui est vendu | Le catalogue l'implémente |
| `Port\OptionDrawer` | port | Tire la valeur d'une option pour une unité | L'application ou un module l'implémente |
| `ConfiguredTaxRates` | `final readonly class`, `TaxRateResolver` | Les taux de `phil_commerce.tax_rates` | Implémenté et testé |
| `Channels` | `final readonly class` | Le canal d'une requête, les commandes qu'ouvre un canal | Implémenté et testé |
| `Port\SellableAvailability` | port | Ce qu'un canal offre de quelque chose : listé, par lien direct seulement ou caché ; commandable ou non ; avec prix ou non | Le catalogue l'implémente |
| `Payments` | `final readonly class` | Commence les paiements, applique les notifications, demande les remboursements, additionne une commande | Implémenté et testé |
| `NotificationOutcome` | énumération adossée à une chaîne | Appliquée, déjà appliquée, refusée, paiement inconnu, ignorée | Implémenté et testé |
| `Port\PaymentGateway` | port | Un processeur de paiement | L'application l'implémente |
| `Receipts` | `final readonly class` | Émet les reçus et les notes de crédit | Implémenté et testé |
| `Quotes` | `final readonly class` | Émet, accepte et refuse les soumissions | Implémenté et testé |
| `OrderAmendments` | `final readonly class` | Rouvre une commande passée sans reçu ; ce qui a été payé en trop | Implémenté et testé |
| `DeferredSales` | `final readonly class` | Ouvre, suit, appelle et annule les ventes différées ; ce qui est dû, si la marchandise peut partir | Implémenté et testé |
| `Contract\StockCommitments` | port | Retient et libère le stock d'une commande payée plus tard | Le module de logistique ou l'application l'implémente |
| `Port\DocumentNumbering` | port | Le prochain numéro d'une série, sans trou | Implémenté par `DoctrineDocumentNumbering` |
| `Port\SellableResolver` | port | Ce que vend une référence | Les modules et l'application l'implémentent |
| `Port\UnitPriceResolver` | port | Un prix unitaire autre que le prix de base | Les modules et l'application l'implémentent |
| `Port\OrderProcessor` | port | Recalcule un type d'ajustement | Les modules et l'application l'implémentent |
| `Port\TaxRateResolver` | port | Les taux de `phil/tax` qui s'appliquent à une vente | `ConfiguredTaxRates`, ou celui de l'application |
| `Port\PaymentAttemptStateStore` | port | Un état par clé avec une durée de vie | L'application l'implémente |

## `Phil\CommerceBundle\Infrastructure`

| Type | Nature | Rôle | État |
|---|---|---|---|
| `Doctrine\DoctrineOrderRepository` | `final readonly class`, `OrderRepository` | Les commandes par le gestionnaire d'entités | Implémenté et testé |
| `Doctrine\Type\OrderLinesType` | type DBAL `phil_commerce_order_lines` | Les lignes d'une commande dans une colonne JSON | Implémenté et testé |
| `Doctrine\Type\AdjustmentsType` | type DBAL `phil_commerce_adjustments` | Les ajustements d'une commande dans une colonne JSON | Implémenté et testé |
| `Doctrine\DoctrineChannelRepository` | `final readonly class`, `ChannelRepository` | Les canaux par le gestionnaire d'entités | Implémenté et testé |
| `Doctrine\DoctrinePaymentRepository` | `final readonly class`, `PaymentRepository` | Les paiements par le gestionnaire d'entités | Implémenté et testé |
| `Doctrine\DoctrineReceiptRepository` | `final readonly class`, `ReceiptRepository` | Les reçus par le gestionnaire d'entités | Implémenté et testé |
| `Doctrine\DoctrineQuoteRepository` | `final readonly class`, `QuoteRepository` | Les soumissions par le gestionnaire d'entités | Implémenté et testé |
| `Doctrine\DoctrineDeferredSaleRepository` | `final readonly class`, `DeferredSaleRepository` | Une vente par le gestionnaire d'entités ; les soldes échus par DBAL | Implémenté et testé |
| `Pdf\ReceiptPdf` | `final readonly class` | Un reçu ou une note de crédit sous forme de facture PDF | Implémenté et testé |
| `Doctrine\DoctrineDocumentNumbering` | `final readonly class`, `DocumentNumbering` | Une rangée par série, verrouillée jusqu'à la fin de la transaction | Implémenté et testé |
| `Doctrine\DoctrinePaymentLock` | `final readonly class`, `PaymentLock` | La rangée du paiement verrouillée jusqu'à la fin de la transaction, sa version vérifiée, ses remboursements lus par une lecture verrouillante | Implémenté et testé |
| `Doctrine\Entity\DocumentSequence` | `final class`, entité Doctrine | Le dernier numéro d'une série | Implémenté et testé |
| `Doctrine\DoctrineWishListRepository`, `DoctrinePaymentLinkRepository`, `DoctrineOrderChangeRepository`, `DoctrineOrderJournal`, `DoctrineRefundRepository` | `final readonly class` | Les nouveaux enregistrements par le gestionnaire d'entités | Implémenté et testé |
| `Doctrine\BinaryKeysListener` | `final readonly class` | Les références des paiements et les propriétaires des listes de souhaits en `utf8mb4_bin` sur MySQL et MariaDB, à l'événement `loadClassMetadata` de l'ORM | Implémenté et testé |
| `Workflow\OrderWorkflowPass` | passe de compilation | Ajoute le workflow des commandes à `workflow.registry` | Implémenté et testé |
| `Http\PaymentWebhookController` | `final readonly class`, contrôleur | Les notifications des processeurs, répondues par 204, 400 ou 404 | Implémenté et testé |

## Le bundle

| Type | Nature | Rôle | État |
|---|---|---|---|
| `PhilCommerceBundle` | `final class`, `AbstractBundle` | Alias `phil_commerce` ; `tax_rates` ; enregistre `OrderModifier`, les processeurs de taxes et d'options et `OptionDraws` ; étiquette les neuf ports, que lisent `OrderModifier`, `LineOptions`, `Payments`, `OptionDraws`, `DeferredSales` et `DocumentRegister` ; ajoute le workflow des commandes au registre ; vérifie les préfixes des reçus contre la colonne du numéro ; mappe et stocke les commandes avec DoctrineBundle | Implémenté et testé |

## Pas commencé

| Partie | Paquet |
|---|---|
| Créditer une partie de la quantité d'une ligne | Noyau |
| Les trois niveaux de visibilité de ce qui est vendu — listé, par lien direct seulement, absent | Noyau, avec le catalogue |

Chaque paquet de module tient dans son README sa propre liste de ce qu'il n'a pas commencé ; voyez
[modules.md](modules.md) pour les paquets de modules et les choix déjà consignés pour eux.
