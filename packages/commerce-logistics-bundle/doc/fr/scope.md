<!-- phil-dev source-sha256=a65ab772cbfa715a257ebe5fd1b88ee60c55368beaa84bb8ad468e22fb76b0ab -->
<!-- phil-dev source-sha256=0 -->
# Portée

La logistique du noyau du commerce : le stock par *SKU* et par emplacement, son journal, la réception, les comptages,
les transferts et les réservations des commandes passées, avec les commandes en attente par *SKU* ; le stock des
articles de convention et l'impression à la demande ; les bons de commande et les numéros de série ; les expéditions en
un colis ou en plusieurs, leurs bordereaux de livraison et leurs listes de prélèvement, et les vagues d'expédition ; les
modes de livraison par zone ; les tarifs et les étiquettes des transporteurs avec le coût de chaque expédition.

| Partie | Contenu | État |
|---|---|---|
| Stock | Les niveaux par *SKU* et par emplacement, le journal de chaque mouvement, la réception, les comptages, les transferts, les réservations des commandes passées, libérées ou expédiées, les commandes en attente par *SKU*, le stock des ventes différées du noyau | Implémenté et testé ([stock.md](stock.md)) |
| Conventions | Les quantités préparées par convention et par *SKU*, ce qui reste à imprimer, les commandes passées en ligne envoyées à l'impression tant qu'une convention est proche | Implémenté et testé ([conventions.md](conventions.md)) |
| Livraison | Les modes par zone — expédition, livraison locale, ramassage —, leur coût et leur seuil de gratuité, les jours estimés ; facturés par un `OrderProcessor` | Implémenté et testé ([delivery.md](delivery.md)) |
| Achats | Les bons de commande et la réception contre ceux-ci | Implémenté et testé ([purchasing.md](purchasing.md)) |
| Transporteurs | Les tarifs et les étiquettes par `CarrierGateway`, l'adaptateur de Postes Canada, le coût de chaque expédition à côté de la livraison facturée | Implémenté et testé ([carriers.md](carriers.md)) |
| Expéditions | Une commande expédiée en un colis ou en plusieurs, à partir de ses réservations, avec le transporteur et le suivi ; les bordereaux de livraison et les listes de prélèvement ; les vagues d'expédition | Implémenté et testé ([shipments.md](shipments.md)) |
| Numéros de série | Le numéro de série de chaque unité, gardé avec son stock | Implémenté et testé ([purchasing.md](purchasing.md)) |

## Hors du module

- Les retours aux fournisseurs, le réapprovisionnement et les prévisions.
- Fixer le prix de livraison d'après le tarif d'un transporteur, et taxer des frais de livraison.
- Générer les *SKU* et les motifs des articles imprimés : le module lit les *SKU* des lignes de la commande.

## Les ports du noyau qu'il implémente

- `OrderProcessor`, pour les frais de livraison : `ShippingProcessor`, après les promotions et avant les taxes.
- `StockCommitments`, pour le stock des ventes payées plus tard : `DeferredStock`.
- `DocumentTypes`, pour le bordereau de livraison et la liste de prélèvement : `LogisticsDocumentTypes`.
