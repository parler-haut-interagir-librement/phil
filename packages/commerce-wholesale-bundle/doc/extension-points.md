# Extension points

What the module plugs into `phil/commerce-bundle`, and what an application extends or relies on. The ports of the
core and their contracts are described there.

## The core's ports it implements

| Port | Service | Tag and order |
|---|---|---|
| `UnitPriceResolver` | `QuantityGuard`, the quantity rules | `phil_commerce.unit_price_resolver`, before the catalogue's price books and `GroupPrices` |
| `UnitPriceResolver` | `GroupPrices`, a customer group's prices | `phil_commerce.unit_price_resolver`, after the catalogue's price books |
| `PaymentGateway` | `AccountGateway`, for the method `on_account` | `phil_commerce.payment_gateway` |

A price resolver of the application's own names `GroupPrices` in its tag's `before` or `after`. See [Scope](scope.md).

## Validation

`AllowedQuantityValidator` is tagged `validator.constraint_validator`; the constraint `AllowedQuantity` checks a
`RequestedQuantity` against the rules — see [Quantity rules](quantities.md). Its messages are keys of the
`validators` domain, which the bundle's `translations/` translates in English and French; an application overrides
them in its own translations, or gives the constraint messages of its own.

## Repositories

`CustomerGroupRepository`, `QuantityRuleRepository` and `CreditRepository` are public aliases of
`DoctrineWholesaleRepository`; an application points them at a service of its own under the same contracts.

## Services

| Service | How an application relies on it |
|---|---|
| `CustomerGroups` | Groups, their prices and minimum order, and who belongs; public — see [Groups](groups.md) |
| `QuantityRules` | The quantity rules of SKUs per group: set, changed, removed, the rule a line follows; public — see [Quantity rules](quantities.md) |
| `CreditSales` | Accounts with a limit and net terms, receivables, payments, what is overdue; public — see [Credit](credit.md) |
