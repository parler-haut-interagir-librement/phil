<!-- phil-dev source-sha256=bb4272955552d48e8f0d3adedd9e13d3f0c6f8d74b408b1e24ff622a08650ea7 -->
<!-- phil-dev source-sha256=0 -->
# Portée

La syndication de catalogue entre des applications distinctes du noyau du commerce.

| Partie | Contenu | État |
|---|---|---|
| Partenaires | Les fournisseurs et les revendeurs, leurs points de terminaison et leurs clés publiques, la suspension | Implémenté et testé ([syndication.md](syndication.md)) |
| Flux | Le prix, le coût, le stock et le retrait de chaque produit, à chaque revendeur à qui il est fourni | Implémenté et testé |
| Produits syndiqués | Les produits d'un fournisseur vendus ici, comme vendables du noyau | Implémenté et testé |
| Transmission | Les lignes syndiquées de chaque commande envoyées à leurs fournisseurs, une seule fois | Implémenté et testé |
| Registre | Ce que chaque partenaire doit par commande, les soldes, le règlement | Implémenté et testé |
| Messages | Des Standard Webhooks signés avec Ed25519, une boîte d'envoi avec nouvelles tentatives, la déduplication à la réception | Implémenté et testé ([messages.md](messages.md)) |
| Suivi des expéditions | Le fournisseur qui dit au revendeur chaque colis de la commande, avec son transporteur et son numéro de suivi | Implémenté et testé ([syndication.md](syndication.md)) |
| Retours | Un retour chez le revendeur porté au fournisseur, et son crédit dans les deux registres | Implémenté et testé ([syndication.md](syndication.md)) |
| Contenu enrichi | Les descriptions, les images et les attributs dans le flux | Pas commencé |
| Synchronisation initiale | L'envoi du flux complet à un revendeur lors de sa première alimentation | Pas commencé : l'application publie chaque UGS |

## Les ports du noyau qu'il implémente

- `SellableResolver` : `SyndicatedSellables`, pour le type `syndicated`.
- `SellableAvailability` : `SyndicatedSellables`, qui cache un produit retiré ou en rupture de stock dans tous les
  canaux, et donc le refuse.

## Ses propres ports

- `MessageTransport` : transporte un message signé ; `HttpClientTransport` par le client HTTP de Symfony.
- `ForwardedOrderHandler` : ce que fait le fournisseur d'une commande qu'un revendeur lui a transmise.
- `PartnerShipmentHandler` : ce que fait le revendeur une fois que son fournisseur a expédié un colis.
- `PartnerReturnHandler` : ce que fait chaque côté quand un retour est demandé et reçu.
