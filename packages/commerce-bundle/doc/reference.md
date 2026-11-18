# Reference

Every public type of the bundle, by layer, and the parts of the design not started yet. Amounts and currencies
are the `Money` and `Currency` of `phil/money` ([money.md](money.md)); rates, lines, breakdowns and their calculator
are the types of `phil/tax` ([taxes.md](taxes.md)).

## `Phil\CommerceBundle\Domain`

| Type | Kind | Role | State |
|---|---|---|---|
| `Model\Channel` | `final class`, Doctrine entity | A site or a point of sale: domains, currency, locale, theme, tax zone | Implemented and tested |
| `Repository\ChannelRepository` | interface | Finds and stores channels | Implemented by `DoctrineChannelRepository` |
| `Exception\UnknownChannel`, `Exception\NotSoldThere` | `final class`, `DomainError` | No open channel by host or code; a sellable outside the channel's selection | Implemented and tested |
| `Model\Order` | `final class`, Doctrine entity | Lines, adjustments, tax zone, channel, customer group, promotion codes, state, version; computed totals | Implemented and tested |
| `Order\OrderState` | string-backed enum | `Cart`, `Quoted`, `Placed`, `Cancelled` | Implemented and tested |
| `Model\Quote`, `Order\QuoteState` | `final class`, Doctrine entity; enum | A quote, `Pending`, `Accepted`, `Declined` | Implemented and tested |
| `Exception\QuoteRefused` | `final class`, `DomainError` | A quote unknown, answered or expired; an order whose receipt is issued | Implemented and tested |
| `Repository\QuoteRepository` | interface | Quotes | Implemented by `DoctrineQuoteRepository` |
| `Repository\OrderRepository` | interface | Finds and stores orders | Implemented by `DoctrineOrderRepository` |
| `Exception\OrderLocked` | `final class`, `DomainError` | A change or a move the order's state refuses | Implemented and tested |
| `Model\DeferredSale` | `final class`, Doctrine entity | A placed order paid later: deposit, trigger, stock commitment, fulfilment, due date, state | Implemented and tested |
| `ValueObject\DeferredSalePolicy`, `ValueObject\Deposit` | `final readonly class` | How a sale is paid later; what is asked first | Implemented and tested |
| `Deferred\BalanceTrigger`, `Deferred\StockCommitment`, `Deferred\Fulfilment`, `Deferred\DeferredSaleState` | string-backed enums | `Date`, `Shipment`, `Availability`, `Manual`; `OnOrder`, `OnBalance`; `OnDeposit`, `OnBalance`; the five states | Implemented and tested |
| `Event\DeferredSaleEvent` | `abstract readonly class`, `DomainEvent` | `DeferredSaleOpened`, `DepositReceived`, `BalanceCalled`, `DeferredSaleSettled`, `DeferredSaleCancelled`, `StockCommitted`, `StockReleased` | Implemented and tested |
| `Contract\DeferredSaleRepository` | interface | Deferred sales and the balances due | Implemented by `DoctrineDeferredSaleRepository` |
| `Exception\DeferredSaleRefused` | `final class`, `DomainError` | A deferred sale that cannot be opened, called or cancelled | Implemented and tested |
| `Model\Payment` | `final class`, Doctrine entity | One payment: amount, refunded, state, the processor's reference, notifications applied | Implemented and tested |
| `Payment\PaymentState` | string-backed enum | The nine states of a payment | Implemented and tested |
| `Payment\PaymentEvent` | string-backed enum | What a notification says | Implemented and tested |
| `Payment\OrderPaymentState` | string-backed enum | Unpaid, partially paid, paid, partially refunded, refunded | Implemented and tested |
| `ValueObject\PaymentNotification` | `final readonly class` | A verified notification | Implemented and tested |
| `ValueObject\PaymentStart` | `final readonly class` | The processor's reference, and a page or a client secret | Implemented and tested |
| `ValueObject\OrderPayments` | `final readonly class` | An order's payments summed | Implemented and tested |
| `Repository\PaymentRepository` | interface | Finds and stores payments | Implemented by `DoctrinePaymentRepository` |
| `Exception\PaymentRefused` | `final class`, `DomainError` | A payment that cannot be started, refunded or moved | Implemented and tested |
| `Model\Receipt` | `final class`, Doctrine entity | A receipt or a credit note, never edited | Implemented and tested |
| `Receipt\DocumentKind` | string-backed enum | `Receipt`, `CreditNote` | Implemented and tested |
| `ValueObject\Issuer` | `final readonly class` | Name, address, registration numbers | Implemented and tested |
| `Repository\ReceiptRepository` | interface | Finds and stores receipts and credit notes | Implemented by `DoctrineReceiptRepository` |
| `Exception\ReceiptRefused` | `final class`, `DomainError` | A receipt or a credit note that cannot be issued | Implemented and tested |
| `Exception\InvalidNotification` | `final class`, `DomainError` | A notification its gateway cannot trust or read | Implemented and tested |
| `ValueObject\OrderLine` | `final readonly class` | What is sold, quantity, unit price, tax category, configuration; subtotal | Implemented and tested |
| `ValueObject\SellableReference` | `final readonly class` | Type and identifier of what a line sells, and a configured line's fingerprint | Implemented and tested |
| `ValueObject\Sellable` | `final readonly class` | Description, base unit price, tax category, options | Implemented and tested |
| `Channel\Visibility` | `enum` | How far a channel shows a sellable: `Listed`, `Unlisted` (direct link only), `Hidden`; `isListed()`, `isReachable()`, `stricter()` | Implemented and tested |
| `Channel\Offer` | `final readonly class` | What a channel offers of a sellable: `visibility`, `orderable`, `priceShown`; `of()`, `full()`, `hidden()`, `stricter()`, `isListed()`, `isReachable()` | Implemented and tested |
| `ValueObject\OptionSet` | `final readonly class` | The options of a sellable; checks a configuration, gives its supplements | Implemented and tested |
| `ValueObject\OptionDefinition` | `final readonly class` | One option: text, choice, ink, draw or amount | Implemented and tested |
| `ValueObject\AmountRange` | `final readonly class` | The amounts a buyer may choose: `minimum`, `maximum`; `read()`, `refusal()` | Implemented and tested |
| `ValueObject\OptionChoice` | `final readonly class` | One choice or ink: code, label, image, supplement | Implemented and tested |
| `ValueObject\TextConstraints`, `ValueObject\PrintZone` | `final readonly class` | A text's length, characters, font, zone, sources and entries; a zone in millimetres | Implemented and tested |
| `Option\OptionKind`, `Option\TextSource` | string-backed enums | `Text`, `Choice`, `Ink`, `Draw`, `Amount`; `Free`, `List`, `Drawn` | Implemented and tested |
| `ValueObject\LineConfiguration`, `ValueObject\OptionValue` | `final readonly class` | A line's option values, their fingerprint and draws; one value, chosen or to draw | Implemented and tested |
| `Exception\InvalidConfiguration`, `Exception\DrawRefused` | `final class`, `DomainError` | A configuration the options refuse; draws refused before payment or without a drawer | Implemented and tested |
| `ValueObject\PricingContext` | `final readonly class` | Quantity, moment, customer group, channel | Implemented and tested |
| `ValueObject\Adjustment` | `final readonly class` | Type, label, signed amount, line, neutral, origin | Implemented and tested |
| `Adjustment\AdjustmentType` | string-backed enum | `Option`, `Promotion`, `Proration`, `Tax`, `Shipping`, `Fee`, `Tip` | Implemented and tested |
| `Exception\LineNotInOrder` | `final class`, `DomainError` | A change or adjustment names a line the order lacks | Implemented and tested |
| `Exception\UnknownSellable` | `final class`, `DomainError` | Nothing sellable is known by a reference | Implemented and tested |
| `ValueObject\PaymentAttemptPolicy` | `final readonly class` | Budget, penalty step, window | Implemented and tested |
| `ValueObject\PaymentAttemptKey` | `final readonly class` | Order key, or client network key | Implemented and tested |
| `ValueObject\PaymentAttemptState` | `final readonly class` | Attempts, cumulative wait, last attempt | Implemented and tested |
| `ValueObject\PaymentAttemptDecision` | `final readonly class` | Allowed, or retry at; `Retry-After`; most restrictive | Implemented and tested |
| `Service\PaymentAttemptLimiter` | `final readonly class` | `decide()`, `record()`, `timeToLiveSeconds()` | Implemented and tested |
| `Model\WishList`, `ValueObject\WishListItem` | Doctrine entity, value object | A wish list shared by a link, what it wishes for and what was bought | Implemented and tested |
| `Model\PaymentLink` | Doctrine entity | A link that opens a placed order until it expires | Implemented and tested |
| `Model\OrderChange`, `Change\OrderChangeState`, `ValueObject\OrderChangeLine`, `Exception\OrderChangeRefused` | Doctrine entity, enum, value object, `DomainError` | A change of a placed order, prepared, confirmed or abandoned | Implemented and tested |
| `Model\OrderStateChange` | read-only Doctrine entity | One move of an order, journalled | Implemented and tested |
| `Model\Refund`, `Refund\RefundState` | Doctrine entity, enum | A refund granted, executed or withdrawn | Implemented and tested |
| `ValueObject\GiftOptions` | `final readonly class` | Wrapping, recycled wrap, message, hidden prices | Implemented and tested |
| `ValueObject\ManualDiscount` | `final readonly class` | A discount granted by hand, amount or share, on a line or the order | Implemented and tested |
| `ValueObject\DocumentType` | `final readonly class` | A type of the register: code, prefix, digits, visibility | Implemented and tested |
| `ValueObject\Identifier` | `final readonly class` | Checks a stable identifier, never an email address | Implemented and tested |
| `ValueObject\ExpirySweep` | `final readonly class` | What a sweep expired and deleted | Implemented and tested |
| `Event\OrderPurchased`, `Event\OrderRefunded`, `Event\OrderCancelled`, `Event\OrderStateForced` | `DomainEvent` | An order paid in full; a refund executed; an order cancelled, expired or forced to cancelled; an order forced | Implemented and tested |
| `Contract\OrderJournal`, `Contract\WishListRepository`, `Contract\PaymentLinkRepository`, `Contract\OrderChangeRepository`, `Contract\RefundRepository`, `Contract\ExpiringOrders` | contracts | Storage of the records above | Implemented by their Doctrine adapters |

## `Phil\CommerceBundle\Application`

| Type | Kind | Role | State |
|---|---|---|---|
| `OrderWorkflow`, `OrderMarkingStore` | `final class`, marking store | The `phil_commerce_order` state machine and its places | Implemented and tested |
| `OrderLifecycle` | `final readonly class` | Moves, journals and forces orders, sets their statuses | Implemented and tested |
| `DenyForcing`, `Contract\TransitionForcing` | `final readonly class`, port | Who may force an order; nobody by default | Implemented and tested |
| `UnknownShipments`, `Contract\OrderShipments` | `final readonly class`, port | When an order or a line left the store; unknown by default | Implemented and tested |
| `GiftWrapping` | `final readonly class`, `OrderProcessor` | The wrapping a store offers, charged once per wrapped order | Implemented and tested |
| `ManualDiscountProcessor` | `final readonly class`, `OrderProcessor` | The discounts granted by hand, before the taxes | Implemented and tested |
| `WishLists` | `final readonly class` | Wish lists, shared by a link, bought from, counted on `OrderPurchased` | Implemented and tested |
| `PaymentLinks` | `final readonly class` | Links that open a placed order until they expire | Implemented and tested |
| `OrderChanges` | `final readonly class` | Changes of placed orders, prepared, confirmed and versioned | Implemented and tested |
| `OrderExpiry` | `final readonly class` | Expires unpaid orders and deletes expired ones, per channel | Implemented and tested |
| `Refunds` | `final readonly class` | Refunds granted then executed | Implemented and tested |
| `DocumentRegister`, `CoreDocumentTypes`, `Contract\DocumentTypes`, `Contract\DocumentSeries` | `final class`, `final readonly class`, ports | The register of document types, their series and their visibility; `types()`, `listed()`, `type()`, `next()` | Implemented and tested |
| `SecretToken` | `final class` | The secret of a link, shown once, stored as its SHA-256 | Implemented and tested |
| `OrderModifier` | `final readonly class` | Changes an order through the ports and recomputes it | Implemented and tested |
| `SellableOffers` | `final readonly class` | What a channel offers of a sellable, the strictest answer of every selection | Implemented and tested |
| `TaxOrderProcessor` | `final readonly class`, `OrderProcessor` | Taxes each line with its options, after its promotions | Implemented and tested |
| `LineOptions` | `final readonly class` | The options of a reference, from the `SellableOptions` of its type | Implemented and tested |
| `OptionSupplementProcessor` | `final readonly class`, `OrderProcessor` | Charges what the options of each line add | Implemented and tested |
| `OptionDraws` | `final readonly class` | Draws the options of a paid order | Implemented and tested |
| `Port\SellableOptions` | port | The options of what is sold | The catalogue implements it |
| `Port\OptionDrawer` | port | Draws an option's value for one unit | The application or a module implements it |
| `ConfiguredTaxRates` | `final readonly class`, `TaxRateResolver` | The rates of `phil_commerce.tax_rates` | Implemented and tested |
| `Channels` | `final readonly class` | The channel of a request, the orders a channel opens | Implemented and tested |
| `Port\SellableAvailability` | port | What a channel offers of something: listed, by direct link only or hidden; orderable or not; priced or not | The catalogue implements it |
| `Payments` | `final readonly class` | Starts payments, applies notifications, asks refunds, sums an order | Implemented and tested |
| `NotificationOutcome` | string-backed enum | Applied, already applied, refused, unknown payment | Implemented and tested |
| `Port\PaymentGateway` | port | One payment processor | The application implements it |
| `Receipts` | `final readonly class` | Issues receipts and credit notes | Implemented and tested |
| `Quotes` | `final readonly class` | Issues, accepts and declines quotes | Implemented and tested |
| `OrderAmendments` | `final readonly class` | Reopens a placed order without receipt; what it was overpaid | Implemented and tested |
| `DeferredSales` | `final readonly class` | Opens, follows, calls and cancels deferred sales; what is due, whether the goods may leave | Implemented and tested |
| `Contract\StockCommitments` | port | Holds and frees the stock of an order paid later | The logistics module or the application implements it |
| `Port\DocumentNumbering` | port | The next number of a series, without gaps | Implemented by `DoctrineDocumentNumbering` |
| `Port\SellableResolver` | port | What a reference sells | Modules and the application implement it |
| `Port\UnitPriceResolver` | port | A unit price other than the base price | Modules and the application implement it |
| `Port\OrderProcessor` | port | Recomputes one type of adjustment | Modules and the application implement it |
| `Port\TaxRateResolver` | port | Which rates of `phil/tax` apply to a sale | `ConfiguredTaxRates`, or the application's |
| `Port\PaymentAttemptStateStore` | port | One state per key with a time-to-live | The application implements it |

## `Phil\CommerceBundle\Infrastructure`

| Type | Kind | Role | State |
|---|---|---|---|
| `Doctrine\DoctrineOrderRepository` | `final readonly class`, `OrderRepository` | Orders through the entity manager | Implemented and tested |
| `Doctrine\Type\OrderLinesType` | DBAL type `phil_commerce_order_lines` | An order's lines in one JSON column | Implemented and tested |
| `Doctrine\Type\AdjustmentsType` | DBAL type `phil_commerce_adjustments` | An order's adjustments in one JSON column | Implemented and tested |
| `Doctrine\DoctrineChannelRepository` | `final readonly class`, `ChannelRepository` | Channels through the entity manager | Implemented and tested |
| `Doctrine\DoctrinePaymentRepository` | `final readonly class`, `PaymentRepository` | Payments through the entity manager | Implemented and tested |
| `Doctrine\DoctrineReceiptRepository` | `final readonly class`, `ReceiptRepository` | Receipts through the entity manager | Implemented and tested |
| `Doctrine\DoctrineQuoteRepository` | `final readonly class`, `QuoteRepository` | Quotes through the entity manager | Implemented and tested |
| `Doctrine\DoctrineDeferredSaleRepository` | `final readonly class`, `DeferredSaleRepository` | One sale through the entity manager; the balances due through DBAL | Implemented and tested |
| `Pdf\ReceiptPdf` | `final readonly class` | A receipt or a credit note as a PDF invoice | Implemented and tested |
| `Doctrine\DoctrineDocumentNumbering` | `final readonly class`, `DocumentNumbering` | A row per series, locked until the transaction ends | Implemented and tested |
| `Doctrine\Entity\DocumentSequence` | `final class`, Doctrine entity | The last number of a series | Implemented and tested |
| `Doctrine\DoctrineWishListRepository`, `DoctrinePaymentLinkRepository`, `DoctrineOrderChangeRepository`, `DoctrineOrderJournal`, `DoctrineRefundRepository` | `final readonly class` | The new records through the entity manager | Implemented and tested |
| `Doctrine\BinaryKeysListener` | `final readonly class` | The payments' references and the wish lists' owners in `utf8mb4_bin` on MySQL and MariaDB, at the ORM's `loadClassMetadata` | Implemented and tested |
| `Workflow\OrderWorkflowPass` | compiler pass | Adds the order workflow to `workflow.registry` | Implemented and tested |
| `Http\PaymentWebhookController` | `final readonly class`, controller | The processors' notifications, answered 204, 400 or 404 | Implemented and tested |

## The bundle

| Type | Kind | Role | State |
|---|---|---|---|
| `PhilCommerceBundle` | `final class`, `AbstractBundle` | Alias `phil_commerce`; `tax_rates`; registers `OrderModifier`, the tax and option processors and `OptionDraws`; tags the nine ports, read by `OrderModifier`, `LineOptions`, `Payments`, `OptionDraws`, `DeferredSales` and `DocumentRegister`; adds the order workflow to the registry; checks the receipt prefixes against the number column; maps and stores orders with DoctrineBundle | Implemented and tested |

## Not started

| Part | Package |
|---|---|
| Crediting part of a line's quantity | Core |
| The three visibility levels of what is sold — listed, direct link only, absent | Core, with the catalogue |

Each module package keeps its own list of what it has not started in its README; see [modules.md](modules.md)
for the module packages and the choices already recorded for them.
