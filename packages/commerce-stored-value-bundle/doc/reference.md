# Reference

Every public type of the module.

| Type | Kind | Role |
|---|---|---|
| `PhilCommerceStoredValueBundle` | `final class`, `AbstractBundle` | Alias `phil_commerce_stored_value`; refuses to load without `PhilCommerceBundle` |
| `Domain\Model\Instrument` | `final class`, Doctrine entity | A gift card or a store credit, its balance |
| `Domain\Model\Movement` | `final class`, Doctrine entity | One change of a balance |
| `Domain\Instrument\InstrumentKind` | enum | `GiftCard`, `StoreCredit` |
| `Domain\Instrument\GiftCardOrigin` | enum | `Given`, `Paid`: whether a gift card may expire |
| `Domain\Instrument\MovementKind` | enum | `Issued`, `Loaded`, `Granted`, `Redeemed`, `Restored`, `Voided` |
| `Domain\ValueObject\GiftCardCode` | `final readonly class` | A code: generated, read, hashed |
| `Domain\Exception\StoredValueRefused` | `final class`, `DomainError` | An operation refused: unknown, voided, expired, insufficient, an order not paid, a refund with nowhere to go |
| `Domain\Repository\InstrumentRepository` | interface | Instruments and movements |
| `Application\GiftCards` | `final readonly class` | Issuing, loading, voiding, finding a card; voiding a cancelled order's cards |
| `Application\Port\StoreCreditHolders` | interface | The customer of an order, for a refund a voided or expired card cannot take |
| `Application\IssuedGiftCard` | `final readonly class` | A card just issued, and its code |
| `Application\StoreCredits` | `final readonly class` | Granting credit, balances |
| `Application\StoredValuePayments` | `final readonly class` | Paying with a card or credit |
| `Application\GiftCardSellables` | `final readonly class`, the core's `SellableResolver` | The sellable type `gift_card` |
| `Application\StoredValueGateway` | `final readonly class`, the core's `PaymentGateway` | The methods `gift_card` and `store_credit` |
| `Infrastructure\Doctrine\DoctrineStoredValueRepository` | `final readonly class` | The repository on the entity manager |
| `Infrastructure\Pdf\GiftCardPdf` | `final readonly class` | A card just issued as a PDF the size of a payment card |

See [scope.md](scope.md) for what is not started.
