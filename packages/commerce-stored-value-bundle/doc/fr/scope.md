<!-- phil-dev source-sha256=84711aea7135b78f665294d34ba1f5e6a9c88d162d19cb3de0f156b2431b64f5 -->
# Portée

La valeur stockée pour le noyau du commerce : les cartes-cadeaux et le crédit en magasin.

| Partie | Contenu | État |
|---|---|---|
| Cartes-cadeaux | Vendues comme vendable, émises par unité d'une commande passée, données, rechargées, annulées ; données ou payées, une expiration pour une carte donnée ou payée pour un service précis | Implémenté et testé ([stored-value.md](stored-value.md)) |
| Codes | Seize caractères, lecture indulgente, enregistrés sous forme d'empreinte | Implémenté et testé |
| Crédit en magasin | Un solde par client et par devise, accordé | Implémenté et testé |
| Paiement | L'un ou l'autre instrument comme paiement du noyau, les paiements partagés, les remboursements vers l'instrument | Implémenté et testé |
| Mouvements | L'historique de chaque solde | Implémenté et testé |
| Envoi du code | L'envoi du code à l'acheteur ou au destinataire | Relève de l'application |
| Limite de débit | La fréquence à laquelle un code peut être essayé | Relève de l'application, par exemple avec `symfony/rate-limiter` |
| Cartes physiques | Des cartes imprimées d'avance, activées à la caisse | Pas commencé |
| Soldes non réclamés | Des rapports sur la valeur jamais dépensée | Pas commencé |

## Les ports du noyau qu'il implémente

- `SellableResolver` : `GiftCardSellables`, pour le type `gift_card`.
- `PaymentGateway` : `StoredValueGateway`, pour les méthodes `gift_card` et `store_credit`.
