<!-- phil-dev source-sha256=a481fd24b9af780aeefd4400405073c015afdfd8b358e86490e0473170201645 -->
# Points d'extension

Ce que le module branche sur `phil/commerce-bundle`, et ce qu'une application étend ou utilise. Les ports du noyau et
leurs contrats y sont décrits.

## Les ports du noyau qu'il implémente

| Port | Service | Étiquette et ordre |
|---|---|---|
| `UnitPriceResolver` | `QuantityGuard`, les règles de quantité | `phil_commerce.unit_price_resolver`, avant les carnets de prix du catalogue et `GroupPrices` |
| `UnitPriceResolver` | `GroupPrices`, les prix d'un groupe de clients | `phil_commerce.unit_price_resolver`, après les carnets de prix du catalogue |
| `PaymentGateway` | `AccountGateway`, pour la méthode `on_account` | `phil_commerce.payment_gateway` |

Un résolveur de prix propre à l'application nomme `GroupPrices` dans le `before` ou le `after` de son étiquette. Voir
[Portée](scope.md).

## La validation

`AllowedQuantityValidator` porte l'étiquette `validator.constraint_validator` ; la contrainte `AllowedQuantity`
vérifie une `RequestedQuantity` contre les règles — voir [Règles de quantité](quantities.md). Ses messages sont des clés
du domaine `validators`, que le dossier `translations/` du bundle traduit en anglais et en français ; une application
les remplace dans ses propres traductions, ou donne à la contrainte ses propres messages.

## Dépôts

`CustomerGroupRepository`, `QuantityRuleRepository` et `CreditRepository` sont des alias publics de
`DoctrineWholesaleRepository` ; une application les fait pointer vers un service à elle sous les mêmes contrats.

## Services

| Service | Comment une application l'utilise |
|---|---|
| `CustomerGroups` | Les groupes, leurs prix et leur commande minimale, et qui en fait partie ; public — voir [Groupes](groups.md) |
| `QuantityRules` | Les règles de quantité des *SKU* par groupe : fixées, changées, retirées, la règle que suit une ligne ; public — voir [Règles de quantité](quantities.md) |
| `CreditSales` | Les comptes avec une limite et des modalités nettes, les créances, les paiements, ce qui est en souffrance ; public — voir [Crédit](credit.md) |
