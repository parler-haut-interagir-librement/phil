<!-- phil-dev source-sha256=656de15aeb32b19503008e3012fbe1e3e8886c239cd9fe07e00223af267e75cd -->
# Modules

Le noyau du commerce est ce bundle ; chaque autre partie est **un paquet à part** qui s'y branche. La plupart des
boutiques vendent des accès plutôt que des biens : elles installent le noyau et peut-être les abonnements, et jamais
le catalogue, la logistique ni la vente en gros.

| Paquet | Classe du bundle | Contient | État |
|---|---|---|---|
| `phil/commerce-bundle` | `PhilCommerceBundle` | Les commandes et les ajustements, les ports ci-dessous, les taxes par ligne, les paiements et leur *webhook*, les reçus et les notes de crédit avec leur facture PDF, le limiteur des tentatives de paiement | Implémenté ; les crédits sur une partie de la quantité à venir |
| `phil/commerce-subscription-bundle` | `PhilCommerceSubscriptionBundle` | Les offres et les niveaux, les périodes, les essais, le renouvellement, le recouvrement, l'annulation à la fin de la période payée, les fonctionnalités par niveau | Implémenté ; voyez son README pour ce qui n'est pas commencé |
| `phil/commerce-catalog-bundle` | `PhilCommerceCatalogBundle` | Les produits, les grilles de prix, les promotions et leurs codes, les produits que vend un canal | Implémenté ; les variantes, la taxonomie, les ensembles et les avis pas commencés |
| `phil/commerce-logistics-bundle` | `PhilCommerceLogisticsBundle` | Le stock par emplacement, les réservations des commandes, les expéditions en colis, les modes de livraison facturés par zone | Implémenté ; voyez son README |
| `phil/commerce-wholesale-bundle` | `PhilCommerceWholesaleBundle` | Les groupes de clients avec leurs propres prix et leur commande minimale, les ventes à crédit avec des conditions nettes | Implémenté ; voyez son README |
| `phil/commerce-pos-bundle` | `PhilCommercePosBundle` | Une caisse enregistreuse pour les ventes au comptoir : les tiroirs-caisses, les quarts de travail, les comptages de caisse, les mises de côté, les ventes enregistrées hors ligne | Implémenté ; voyez son README |
| `phil/commerce-returns-bundle` | `PhilCommerceReturnsBundle` | Les demandes de retour, de l'autorisation à un remboursement, un crédit en magasin ou un échange | Implémenté ; voyez son README |
| `phil/commerce-stored-value-bundle` | `PhilCommerceStoredValueBundle` | Les cartes-cadeaux et le crédit en magasin, vendus, chargés et dépensés comme des paiements | Implémenté ; voyez son README |
| `phil/commerce-syndication-bundle` | `PhilCommerceSyndicationBundle` | Les produits d'une autre boutique revendus, avec les commandes transmises au fournisseur qui expédie | Implémenté ; voyez son README |

Installer un module, c'est l'activer : il n'y a aucune liste de modules activés à tenir. Un module refuse de se
charger sans le noyau.

## Comment un module se branche

Le noyau définit sept étiquettes, que l'autoconfiguration remplit avec chaque service qui implémente le port
correspondant ([extension-points.md](extension-points.md)). `OrderModifier` en lit quatre, `LineOptions` les options,
`Payments` les passerelles et `OptionDraws` les tireurs :

| Port | Étiquette | Implémenté par |
|---|---|---|
| `SellableResolver` | `phil_commerce.sellable_resolver` | Le catalogue (`product`), les abonnements (`offer`), l'application pour tout ce qu'elle vend d'autre |
| `UnitPriceResolver` | `phil_commerce.unit_price_resolver` | Les grilles de prix du catalogue, les listes de prix de la vente en gros |
| `OrderProcessor` | `phil_commerce.order_processor` | Les promotions du catalogue (avant les taxes), les suppléments d'options du noyau (avant les taxes) et les taxes, la livraison de la logistique (après les promotions, avant les taxes), le calcul au prorata des abonnements |
| `SellableAvailability` | `phil_commerce.sellable_availability` | Les sélections et les offres du catalogue, les produits revendus de la syndication |
| `SellableOptions` | `phil_commerce.sellable_options` | Un catalogue, pour les options qu'il déclare sur ses produits |
| `OptionDrawer` | `phil_commerce.option_drawer` | Un module ou l'application, pour chaque valeur tirée après le paiement |
| `PaymentGateway` | `phil_commerce.payment_gateway` | La valeur stockée, la caisse, les retours, les comptes de vente en gros, et une passerelle par processeur de l'application |

Un module ne change jamais le noyau : il ajoute des services qui implémentent ces ports, et ses propres entités et
écrans.
Un module qui numérote des documents à lui déclare leurs types au registre avec `DocumentTypes`, étiqueté
`phil_commerce.document_types` ([documents.md](documents.md)).

Un paquet extérieur au commerce — un programme de fidélité, un fil d'activité — suit les ventes par `OrderPurchased` et
par les événements du workflow `phil_commerce_order`, jamais par une dépendance du noyau envers lui
([payments.md](payments.md#une-commande-payée-en-entier), [lifecycle.md](lifecycle.md)).

## Référence de conception

Le découpage suit les composants de commerce de Sylius 2.3 (Taxation, Order, Inventory, Shipping, Payment,
Promotion, Product, Attribute, Taxonomy, Currency) : la forme des modèles et des calculateurs, et les processeurs de
commande qui recalculent les ajustements. Sylius sert de modèle seulement, et les
bundles se passent de `Sylius\Resource`, de son registre, de ses fabriques et de ses dépôts, ainsi que de
`webmozart/assert` : les objets-valeurs se valident eux-mêmes. Les abonnements n'ont pas d'équivalent dans Sylius et
sont conçus sans modèle.

Les choix consignés pour qu'ils ne soient pas défaits par accident :

- les paiements par des passerelles à nous, une par processeur, plutôt que Payum ;
- l'état du paiement qui ne vient que des notifications signées du processeur, jamais du retour du navigateur
  (construit : [payments.md](payments.md)) ;
- les montants calculés sur le serveur à partir de la commande et de ses ajustements, jamais tirés d'un formulaire
  (construit) ;
- les remboursements et les contestations comme des états du paiement, et une note de crédit qui annule un reçu
  plutôt que de le modifier (construit : [receipts.md](receipts.md)) ;
- les reçus numérotés en séries sans trou (construit).
