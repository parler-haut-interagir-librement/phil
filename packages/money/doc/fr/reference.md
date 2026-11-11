<!-- phil-dev source-sha256=dc058c94aa08355e6e45a7d45327b96c8595a56cbca236f9caf0fff8e5f6eb8e -->
# Référence

Chaque type de la bibliothèque, avec son rôle.

| Type | Nature | Rôle |
|---|---|---|
| `Phil\Money\Currency` | `final class`, `code` en lecture seule, `Stringable`, une instance par code | Un code ISO 4217 connu des données CLDR ; précision, nom et symbole |
| `Phil\Money\Money` | `final readonly class` | Des unités mineures entières avec une devise ; arithmétique, comparaison, décimaux, répartition |
| `Phil\Money\SchemaOrg` | `final class` | `amount(Money)`, `price(Money)` : `MonetaryAmount` et prix d'`Offer` de schema.org |
| `Phil\Money\Exception\MoneyException` | interface, étend `Throwable` | Toutes les exceptions de la bibliothèque |
| `Phil\Money\Exception\UnknownCurrency` | `final class`, `InvalidArgumentException` | Un code que les données CLDR ne connaissent pas |
| `Phil\Money\Exception\InvalidAmount` | `final class`, `InvalidArgumentException` | Un décimal, un montant ou une répartition refusé |
| `Phil\Money\Exception\CurrencyMismatch` | `final class`, `DomainException` | Deux devises réunies dans une même opération |

Voir [currencies.md](currencies.md), [amounts.md](amounts.md), [allocation.md](allocation.md) et
[exceptions.md](exceptions.md).

## schema.org

`SchemaOrg::amount(Money)` donne un `MonetaryAmount`, et `SchemaOrg::price(Money)` donne le `price` et le
`priceCurrency` d'un `Offer`. La valeur est le décimal exact sous forme de texte, puisqu'un nombre à virgule flottante
l'arrondirait. Le tableau a un `@type` et aucun `@context` : il est fait pour être intégré à un document JSON-LD plus
grand.
