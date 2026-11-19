<!-- phil-dev source-sha256=3de4a0532130fd504e8b724fd7af5c1a9b4b9a07653f735146feebf6048739cc -->
# Portée

Les retours pour le noyau du commerce.

| Partie | Contenu | État |
|---|---|---|
| Demandes | Les lignes et les raisons, le délai depuis le passage ou la dernière expédition, les quantités restantes, une limite de débit par client | Implémenté et testé ([returns.md](returns.md)) |
| Étapes | L'autorisation, les questions au client, le refus, l'annulation, l'expiration, la réception avec l'état de chaque ligne | Implémenté et testé |
| Valeur | La part de chaque unité dans sa ligne, les ensembles retournés en entier, les promotions spéciales défaites, les frais de remise en stock, les unités manquantes | Implémenté et testé |
| Remboursements de livraison | Les frais de livraison rendus une fois par commande, comme le dit `delivery_refund` | Implémenté et testé |
| Remise en stock | Le port `Restocking`, appelé pour les unités revendables | Implémenté et testé |
| Règlement | Le remboursement aux paiements, le crédit en magasin, l'échange | Implémenté et testé |
| Notes de crédit partielles | Une note de crédit pour exactement ce qu'un retour rend, numérotée dans la série du noyau | Implémenté et testé ([credit-notes-and-slips.md](credit-notes-and-slips.md)) |
| Bordereaux de retour | Un bordereau numéroté dans sa propre série, produit en PDF | Implémenté et testé ([credit-notes-and-slips.md](credit-notes-and-slips.md)) |
| Étiquettes de retour | L'étiquette d'un transporteur par le port `ReturnLabels` | Implémenté et testé |
| Événements | Chaque étape, chaque règlement, chaque note de crédit | Implémenté et testé |
| Étiquettes des transporteurs du module de logistique | Un adaptateur de `ReturnLabels` sur les transporteurs de `phil/commerce-logistics-bundle`, qui a besoin de l'adresse du client comme origine d'un colis | Pas commencé : l'adaptateur d'une application sert le port en attendant |

## Les ports du noyau qu'il implémente

- `PaymentGateway` : `ReturnsGateway`, pour la méthode `exchange`.
- `DocumentTypes` : `ReturnDocumentTypes`, la série `return_slip`.
- `DocumentNumbering` et `DocumentRegister` sont lus pour les numéros des notes de crédit et des bordereaux.

## Ses propres ports

- `Restocking` : remet des unités sur la tablette.
- `StoreCreditGranter` : accorde un crédit en magasin ; `StoredValueStoreCredit` sur `phil/commerce-stored-value-bundle`.
- `SetParts` : les parties d'un ensemble ; `CatalogSetParts` sur `phil/commerce-catalog-bundle`.
- `SpecialDiscounts` : quels rabais sont des promotions spéciales ; `PromotionSpecials` sur
  `phil/commerce-promotions-bundle`.
- `ShipmentDates` : la dernière expédition d'une commande ; `LogisticsShipmentDates` sur
  `phil/commerce-logistics-bundle`.
- `ReturnLabels` : l'étiquette d'un transporteur ; l'adaptateur de l'application.
- `ReturnSlipRenderer` : un bordereau en PDF ; `PagedMediaReturnSlips` sur `phil/paged-media`.
