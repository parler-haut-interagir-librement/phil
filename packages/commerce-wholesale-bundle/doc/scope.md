# Scope

Wholesale for the core of commerce: customer groups as the model of wholesale selling, their terms, and the buyers
who belong to them.

| Part | Content | State |
|---|---|---|
| Customer groups | A share off the base prices of products, a minimum order | Implemented and tested ([groups.md](groups.md)) |
| Memberships | Each buyer — an account, or a tenant of a multi-tenant application — in one group | Implemented and tested |
| Group prices | The group's share, as the core's `UnitPriceResolver`, after the catalogue's price books | Implemented and tested |
| Quantity rules | A minimum, a maximum and an increment per SKU, for every group or one; a constraint of the Validator, checked in the cart by a `UnitPriceResolver` asked first | Implemented and tested ([quantities.md](quantities.md)) |
| Price lists | A group's own prices: a price book of `phil/commerce-catalog-bundle` naming the group | Through the catalogue |
| Sales on credit | A buyer's account — limit, terms —, purchases on it as payments of the core, receivables, the buyer's payments, overdue | Implemented and tested ([credit.md](credit.md)) |
| Statements, reminders, interest | A monthly statement, a reminder of what is overdue, late fees | Not started |

## The ports of the core it implements

- `UnitPriceResolver`: `GroupPrices`, after the catalogue's price books; `QuantityGuard`, before them and
  `GroupPrices`, which gives no price and refuses a quantity its rule does not allow.
- `PaymentGateway`: `AccountGateway`, for the method `on_account`.
