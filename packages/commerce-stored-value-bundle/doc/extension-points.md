# Extension points

What the module plugs into `phil/commerce-bundle`, and what an application implements, configures or relies on. The
ports of the core and their contracts are described there.

## The core's ports it implements

| Port | Service | Tag |
|---|---|---|
| `SellableResolver` | `GiftCardSellables`, for the type `gift_card` | `phil_commerce.sellable_resolver` |
| `PaymentGateway` | `StoredValueGateway`, for the methods `gift_card` and `store_credit` | `phil_commerce.payment_gateway` |

See [Scope](scope.md).

## `StoreCreditHolders`

`Phil\CommerceStoredValueBundle\Application\Port\StoreCreditHolders` — who an order's customer is, for store credit:
the module knows a gift card by its code, not by whoever holds it. Registered under the interface, it is autowired
into `StoredValueGateway`; without it, a refund that cannot go back to its card — voided, expired — is refused.

| Method | Contract |
|---|---|
| `holderOf(string $orderNumber): ?string` | The customer, named as `StoreCredits` names them, or `null` when the order has none known |

## Repositories

`InstrumentRepository` is a public alias of `DoctrineStoredValueRepository`; a store of the application's own finds a
card by the hash of its code only — a code is never kept in clear.

## Configuration

| Option | What it extends |
|---|---|
| `gift_card.label` | How a gift card line reads in an order |
| `gift_card.tax_category` | The tax category of a gift card sold |
| `gift_card.max_value` | The highest value a gift card is sold at, in minor units |

## Services

`GiftCards`, `StoreCredits` and `StoredValuePayments` issue, load, spend and read; public — see
[Stored value](stored-value.md).
