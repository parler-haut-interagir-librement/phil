<!-- phil-dev source-sha256=dc661adca25571e2eea69ca648c21343f771588cd6f138042f3d1f59e642e603 -->
<!-- phil-dev source-sha256=0 -->
# Portée

La logistique du noyau du commerce : le stock par *SKU* et par emplacement, son journal, la réception, les comptages,
les transferts et les réservations des commandes passées, avec les commandes en attente par *SKU* ; le stock des
articles de convention et l'impression à la demande ; les bons de commande et les numéros de série ; la valeur du stock au coût ; les expéditions en
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
| Calcul des coûts | Le stock de la boutique au coût, en coût moyen pondéré ou en premier entré, premier sorti, les coûts d'approche, les écarts par motif, les retours au coût auquel ils sont partis, les unités en consignation exclues ; lu par `InventoryValuation` | Implémenté et testé ([costing.md](costing.md)) |

## Hors du module

- Les retours aux fournisseurs, le réapprovisionnement et les prévisions.
- Les rapports de marges, de coût des marchandises vendues ou de démarque : le module donne ce qu'ils lisent, par
  `InventoryValuation`, et n'en dessine aucun.
- Ce qui est dû à un consignateur : le module garde les unités d'un propriétaire hors de la valeur de la boutique, et dit
  à qui appartiennent les unités qui partent par `StockOwnership`.
- Fixer le prix de livraison d'après le tarif d'un transporteur, et taxer des frais de livraison.
- Générer les *SKU* et les motifs des articles imprimés : le module lit les *SKU* des lignes de la commande.

## Les ports du noyau qu'il implémente

- `OrderProcessor`, pour les frais de livraison : `ShippingProcessor`, après les promotions et avant les taxes.
- `StockCommitments`, pour le stock des ventes payées plus tard : `DeferredStock`.
- `DocumentTypes`, pour le bordereau de livraison et la liste de prélèvement : `LogisticsDocumentTypes`.
