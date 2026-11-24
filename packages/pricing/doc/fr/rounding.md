<!-- phil-dev source-sha256=2b381dba3aaaf9304a534346af7afa503bcf9341a268b09dc9c06843fb3d19ce -->
# Arrondis et limites

## Chaque arrondi

Chaque division de la bibliothèque se fait en arithmétique entière et dit comment elle arrondit, avec le
`\RoundingMode` de PHP. Chaque méthode qui divise prend le mode en dernier argument ; les valeurs par défaut sont :

| Opération | Valeur par défaut | Pourquoi |
|---|---|---|
| Taux de marge, majoration, conversions, sommaires | `HalfAwayFromZero` | La partie par million la plus proche ; un remboursement a les taux de sa vente |
| Prix cibles | `AwayFromZero` | Vers le haut sur un coût positif : la cible est toujours atteinte |
| Prix suggérés et prix de gros à partir d'une majoration | `AwayFromZero` | Vers le haut : la majoration est toujours atteinte |
| Taux d'un prix de vente face à un prix suggéré | `HalfAwayFromZero` | La partie par million la plus proche |
| Chaque remise d'une cascade, taux effectif | `HalfAwayFromZero` | L'unité la plus proche, comme l'affiche un reçu ; un remboursement est la négation exacte |
| Terminaisons de prix | `PositiveInfinity` | Vers le haut : une terminaison ne coûte jamais de marge |

Les huit modes de `\RoundingMode` sont pris en charge : `HalfAwayFromZero`, `HalfTowardsZero`, `HalfEven`, `HalfOdd`,
`TowardsZero`, `AwayFromZero`, `PositiveInfinity` et `NegativeInfinity`, chacun donnant le résultat que donne
`round()` sur le quotient exact.

## Signes

| Montant | Accepté |
|---|---|
| Prix et coût | Du même signe, ou l'un des deux nul : un remboursement nie les deux. Des signes opposés lèvent `InvalidPricing` |
| Quantité d'une ligne | Tout entier ; une quantité négative est un retour |
| Prix donné à une cascade | Tout signe ; un prix nié donne les étapes niées |
| Prix donné à une terminaison | Zéro ou plus ; un prix négatif lève `InvalidPricing` |
| Coût ou prix de gros majoré en prix suggéré ou en prix de gros | Au-dessus de zéro ; zéro ou négatif lève `InvalidPricing` |
| Prix suggéré, prix de gros unitaire | Au-dessus de zéro, après l'arrondi et la terminaison ; sinon `InvalidPricing` |
| Prix de vente comparé à un prix suggéré | Zéro ou plus ; un prix négatif lève `InvalidPricing` |

## Devises

Tous les montants d'une opération sont dans une seule devise. Deux devises qui se rencontrent lèvent
`Phil\Money\Exception\CurrencyMismatch` de `phil/money` ; la bibliothèque ne convertit jamais.

## Les plus grands montants

La bibliothèque multiplie avant de diviser, dans des entiers natifs. Un taux multiplie un montant par un million : les
montants jusqu'à `PHP_INT_MAX / 1 000 000` unités mineures — environ 92 milliards dans une devise à deux décimales —
sont toujours calculés. Au-delà, un produit qui sort de la plage native lève `AmountOutOfRange` plutôt que de donner un
résultat faux ou à virgule flottante. Un résultat que `Money` ne peut pas contenir lève
`Phil\Money\Exception\InvalidAmount`.
