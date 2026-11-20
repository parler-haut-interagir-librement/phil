# Scope

Stored value for the core of commerce: gift cards and store credit.

| Part | Content | State |
|---|---|---|
| Gift cards | Sold as a sellable, issued per unit of a placed order, given, loaded, voided; given or paid, an expiry for a given card or a paid one for a specific service | Implemented and tested ([stored-value.md](stored-value.md)) |
| Codes | Sixteen characters, forgiving reading, stored as a hash | Implemented and tested |
| Store credit | One balance per customer and currency, granted | Implemented and tested |
| Payment | Either instrument as a payment of the core, split payments, refunds back to the instrument | Implemented and tested |
| Movements | The history of every balance | Implemented and tested |
| Code delivery | Sending the code to the buyer or the recipient | The application's |
| Rate limiting | How often a code may be tried | The application's, for example with `symfony/rate-limiter` |
| Physical cards | Cards printed in advance, activated at the register | Not started |
| Unclaimed balances | Reports of value never spent | Not started |

## The ports of the core it implements

- `SellableResolver`: `GiftCardSellables`, for the type `gift_card`.
- `PaymentGateway`: `StoredValueGateway`, for the methods `gift_card` and `store_credit`.
