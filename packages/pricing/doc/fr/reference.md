<!-- phil-dev source-sha256=611b56df1677bc085624f4b4017253e873b5deaff113b7cf755f26d05bb122c6 -->
# Référence

Chaque type de la bibliothèque, avec son rôle.

| Type | Genre | Rôle |
|---|---|---|
| `Phil\Pricing\Rate` | `final readonly class` | Des parties par million ; lu à partir d'un pourcentage ou de points de base, affiché en pourcentage, comparé |
| `Phil\Pricing\MarginCalculator` | `final readonly class` | Marge, taux de marge et majoration d'un prix et d'un coût ; conversions dans les deux sens ; sommaire de plusieurs lignes |
| `Phil\Pricing\PricedLine` | `final readonly class` | Un prix unitaire, un coût unitaire et une quantité ; revenu, coût total et marge |
| `Phil\Pricing\MarginSummary` | `final readonly class` | Revenu, coût et marge d'une ou plusieurs lignes ; leur taux de marge et leur majoration |
| `Phil\Pricing\PriceCalculator` | `final readonly class` | Le prix pour une marge, une majoration ou une part du coût cibles |
| `Phil\Pricing\DiscountCascade` | `final readonly class` | Des taux de remise successifs, chacun sur ce que le précédent a laissé |
| `Phil\Pricing\DiscountedPrice` | `final readonly class` | Le prix d'origine, les étapes et le prix final ; remise totale et taux effectif |
| `Phil\Pricing\DiscountStep` | `final readonly class` | Une remise : son taux, le prix avant, la remise et le prix après |
| `Phil\Pricing\Contract\PriceEndingRule` | interface | Termine un prix, jamais sous zéro ni sous un plancher |
| `Phil\Pricing\PriceEnding` | `final readonly class`, `PriceEndingRule` | Un pas, une terminaison et un `\RoundingMode` : .99, .95, unités entières... |
| `Phil\Pricing\PriceEndingBands` | `final readonly class`, `PriceEndingRule` | Une règle par tranche de prix, choisie selon le prix avant sa terminaison |
| `Phil\Pricing\SuggestedRetailPrice` | `final readonly class` | Un prix de détail suggéré au-dessus de zéro, avec sa source, sa base et sa majoration ; terminé, comparé |
| `Phil\Pricing\SuggestedRetailPriceSource` | `enum` à valeurs | `Supplier`, `CostMarkup` ou `WholesaleMarkup` |
| `Phil\Pricing\RetailPriceComparison` | `final readonly class` | Un prix de vente face à un prix suggéré : différence, taux signé, taux en dessous |
| `Phil\Pricing\WholesalePriceList` | `final readonly class` | Un prix de gros à partir d'un coût et d'une majoration, avec des paliers de quantité |
| `Phil\Pricing\WholesaleTier` | `final readonly class` | Un palier : à partir de combien d'unités, le prix unitaire, la majoration qui l'a donné |
| `Phil\Pricing\Exception\PricingException` | interface, étend `Throwable` | Chaque exception de la bibliothèque |
| `Phil\Pricing\Exception\InvalidRate` | `final class`, `InvalidArgumentException` | Un pourcentage mal formé, ou un taux sans sens là où il est donné |
| `Phil\Pricing\Exception\InvalidPricing` | `final class`, `DomainException` | Des signes opposés, un prix négatif à terminer, une terminaison invalide, des tranches désordonnées, un coût qui n'est pas au-dessus de zéro à majorer, des paliers désordonnés ou qui haussent le prix |
| `Phil\Pricing\Exception\UndefinedRate` | `final class`, `DomainException` | Un taux demandé à un prix, un coût ou un prix d'origine nul |
| `Phil\Pricing\Exception\AmountOutOfRange` | `final class`, `DomainException` | Un produit intermédiaire hors de la plage des entiers natifs |

`Phil\Pricing\IntegerMath` est interne : la division entière par un `\RoundingMode` et les produits vérifiés que les
autres types partagent. `Phil\Pricing\MarkedUpPrice` est interne aussi : la majoration d'un coût au-dessus de zéro que
les prix suggérés et les prix de gros partagent.

Voir [margins.md](margins.md), [target-prices.md](target-prices.md), [discounts.md](discounts.md),
[price-endings.md](price-endings.md), [suggested-retail-prices.md](suggested-retail-prices.md) et
[rounding.md](rounding.md).
