<!-- phil-dev source-sha256=9cd3dad6b08bf3c121fb44190c773d3cc4a2de8851895c45f526d0a12405921f -->
# Portée

La vente en gros pour le noyau du commerce : les groupes de clients comme modèle de la vente en gros, leurs conditions,
et les acheteurs qui en font partie.

| Partie | Contenu | État |
|---|---|---|
| Groupes de clients | Une remise proportionnelle sur les prix de base des produits, une commande minimale | Implémenté et testé ([groups.md](groups.md)) |
| Appartenances | Chaque acheteur — un compte, ou un locataire d'une application multilocataire — dans un groupe | Implémenté et testé |
| Prix de groupe | La remise du groupe, comme `UnitPriceResolver` du noyau, après les carnets de prix du catalogue | Implémenté et testé |
| Règles de quantité | Un minimum, un maximum et un incrément par *SKU*, pour tous les groupes ou pour un seul ; une contrainte du Validator, vérifiée dans le panier par un `UnitPriceResolver` interrogé en premier | Implémenté et testé ([quantities.md](quantities.md)) |
| Listes de prix | Les prix propres à un groupe : un carnet de prix de `phil/commerce-catalog-bundle` qui nomme le groupe | Par le catalogue |
| Ventes à crédit | Le compte d'un acheteur — limite, modalités —, les achats sur ce compte comme paiements du noyau, les créances, les paiements de l'acheteur, les souffrances | Implémenté et testé ([credit.md](credit.md)) |
| Relevés, rappels, intérêts | Un relevé mensuel, un rappel de ce qui est en souffrance, des frais de retard | Pas commencé |

## Les ports du noyau qu'il implémente

- `UnitPriceResolver` : `GroupPrices`, après les carnets de prix du catalogue ; `QuantityGuard`, avant eux et avant
  `GroupPrices`, qui ne donne aucun prix et refuse une quantité que sa règle ne permet pas.
- `PaymentGateway` : `AccountGateway`, pour la méthode `on_account`.
