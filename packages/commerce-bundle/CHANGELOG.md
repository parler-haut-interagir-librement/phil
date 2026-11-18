# Changelog

All notable changes to `phil/commerce-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.0-alpha - 2026-11-17

The next version is 0.2.0-alpha: a refund is refused that was granted before, and `PaymentGateway::refund()` and
`Payments::refund()` take an idempotency key.

### Added

- `Payment::hasApplied()`: whether a notification of this id, or a refund under this idempotency key, was applied.

### Changed

- `PaymentGateway::refund()` takes a third argument, `string $idempotencyKey`, which an adapter hands to its processor;
  `Payments::refund()` takes it too and passes it on.
- `Payments::refund()` does nothing when the payment already applied the key: a gateway that is its own processor
  records a refund at once under its key, and asking it again — all of the payment or part of it — was refused once
  what is left no longer covered it.

### Security

- `Refunds::grant()` counts the refunds executed and not confirmed yet: once a refund was asked of the processor, it
  no longer counted until the processor's notification, so that 70 and 70 could be granted on a payment of 100. What
  is left is now the payment's amount, less the refunds granted, and less the larger of what the processor confirmed
  and what was asked of it.
- `Refunds::execute()` hands the refund's identifier to the gateway as the idempotency key: a refund asked again,
  after a timeout or a crash, could be made twice by the processor.

## 0.1.0-alpha - 2026-11-15

### Added

- `Order`, `OrderLine`, `Adjustment`, `AdjustmentType` (`Option`, `Promotion`, `Proration`, `Tax`, `Shipping`, `Fee`,
  `Tip`),
  `SellableReference`, `Sellable`, `PricingContext`, `LineNotInOrder`, `UnknownSellable`.
  `Adjustment::$origin`, what gave an adjustment as its processor names it, kept in the `adjustments` JSON column
  under `origin`; a row without the key reads as an adjustment without one. `Order::adjustmentsByLine()`: the
  adjustments on lines grouped by line in one pass.
- Options and configured lines: `OptionSet`, `OptionDefinition` (text, choice, ink, draw, amount), `OptionKind`,
  `AmountRange`, an amount the buyer chooses within it — pay what you want, a donation, a tip — charged as the option's
  supplement, `TextConstraints`, `TextSource`, `PrintZone`, `OptionChoice`, `LineConfiguration`, `OptionValue`,
  `InvalidConfiguration`, `DrawRefused`, `OrderLocked::notPlaced()`; `Sellable::$options` and `withOptions()`;
  `OrderLine::$configuration`, `OrderLine::stored()` and `withDraws()`; `SellableReference::$configuration`, the
  fingerprint of a configured line's values, `configuredBy()`, `unconfigured()` and `fromValue()`, so that two
  configurations of one sellable are two lines; `Order::add()` and `OrderModifier::add()` take a configuration and
  return the line; `Order::recordDraws()`.
- Amounts counted with `phil/money` and taxes with `phil/tax`, which the bundle requires: `TaxRateResolver`, under
  `Application\Port`, returns its `TaxRate`.
- Order states (`OrderState`: cart, quoted, placed, cancelled), `Order::place()`, `Order::cancel()`, `OrderLocked`,
  `OrderModifier::place()`, which places through the order workflow when it is wired and takes who places. Quotes:
  `Order::quote()` and `reopen()`, `Quote`, `QuoteState`, `QuoteRefused`, `QuoteRepository`, `DoctrineQuoteRepository`,
  `Quotes`.
- The channel, the customer group, the delivery method and the promotion codes of an order, read by the price
  resolvers and the order processors; `OrderModifier::changeAudience()`, `chooseDeliveryMethod()`, `enterCode()`
  and `removeCode()`.
- `OrderModifier`, over the ports `SellableResolver`, `UnitPriceResolver` and `OrderProcessor`, which
  autoconfiguration tags `phil_commerce.sellable_resolver`, `phil_commerce.unit_price_resolver` and
  `phil_commerce.order_processor`.
- The `SellableOptions` port, tagged `phil_commerce.sellable_options`, read by `LineOptions` for `OrderModifier` and
  `OptionSupplementProcessor`, which charges what the options add as `Option` adjustments before the taxes; the
  `OptionDrawer` port, tagged `phil_commerce.option_drawer`, read by `OptionDraws`, which draws the options of a paid
  order once per unit.
- Channels: `Channel`, with `expireUnpaidOrders()`, `unpaidExpiryHours()` (24 by default) and `expiredRetentionDays()`
  (30 by default), columns `unpaid_expiry_hours` and `expired_retention_days`; `ChannelRepository` and
  `DoctrineChannelRepository` on `phil_commerce_channel`, `Channels`, `UnknownChannel`; the `SellableAvailability` port,
  tagged `phil_commerce.sellable_availability`, whose `offerThrough()` answers an `Offer` — a `Visibility`, `Listed`,
  `Unlisted` for the direct link only, or `Hidden`, then whether an order may hold it and whether its price is shown
  —, the strictest of every selection given by `SellableOffers` to the pages and read by `OrderModifier`, which
  refuses with `NotSoldThere` a line no order of its channel may hold.
- `TaxOrderProcessor` and `ConfiguredTaxRates`, on the `phil_commerce.tax_rates` configuration, whose dashed tax
  categories, zones and rate codes (`CA-QC`) are kept as written. `TaxOrderProcessor` reads the promotions of every
  line through `Order::adjustmentsByLine()`, so a recalculation is linear in lines and adjustments, and taxes each
  line with its `Option` and `Proration` adjustments.
- Prices that include the taxes: `Order::taxIncluded()`, `changeTaxIncluded()` and `includedTax()`, column
  `tax_included`; `OrderModifier::changeTaxIncluded()`; `TaxOrderProcessor` takes the taxes out of each line and marks
  them neutral; `Receipt::includedTaxes()`; `ReceiptPdf` prints them after the total, labels `included` and
  `taxes_included`.
- Orders stored in one row of `phil_commerce_order` with DoctrineBundle, indexed on the state and the moment placed: the
  attribute mapping, the column types `phil_commerce_order_lines` and `phil_commerce_adjustments`, a version column,
  `OrderRepository` and `DoctrineOrderRepository`. A configured line keeps its configuration in the `lines` column,
  under `configuration`, with its `version` (`LineConfiguration::VERSION`); an adjustment on it keeps the line's
  fingerprint. The mapping runs on SQLite, MySQL 8.4 and PostgreSQL 17: every `lines` column is quoted, and no JSON
  column declares a default.
- Payments: `Payment` and its states, `PaymentEvent`, `PaymentNotification`, `PaymentStart`, `OrderPayments`
  and `OrderPaymentState`, `PaymentRefused`, `InvalidNotification`; the `Payments` service and its outcomes; the
  `PaymentGateway` port, tagged `phil_commerce.payment_gateway`; `PaymentRepository` and
  `DoctrinePaymentRepository` on `phil_commerce_payment`; `PaymentWebhookController`. A refund notification in
  another currency than the payment's is refused (`NotificationOutcome::Refused`, 204). `Payments` takes the
  `OrderRepository`, the event dispatcher, the offline methods and a PSR-20 clock, all optional.
- `Order::countPaymentStart()` and `paymentsStarted()`, column `payments_started`: each payment started moves the
  order's version on, so of two payments started at once over the same copy of an order, the second commit fails as
  stale.
- Abandoned payments: `PaymentState::Abandoned`, `Payment::abandon()`, `Payments::abandon()` and
  `Payments::abandonStale()`; a checkout the buyer left can be abandoned, an abandoned payment frees its share, and a
  late notification still applies to it.
- Amending a placed order: `OrderAmendments`, `OrderPayments::overpaid()`.
- Deferred sales, one per order: `DeferredSalePolicy` (deposit, balance trigger, stock commitment, fulfilment, days to
  the due date) with `preorder()`, `installments()` and `layaway()`, `Deposit`, `BalanceTrigger`, `StockCommitment`,
  `Fulfilment`, `DeferredSaleState`, `DeferredSale` on `phil_commerce_deferred_sale`, `DeferredSaleRefused`; the
  domain events `DeferredSaleOpened`, `DepositReceived`, `BalanceCalled`, `DeferredSaleSettled`,
  `DeferredSaleCancelled`, `StockCommitted` and `StockReleased`, dispatched by their classes; the
  `Application\Contract\StockCommitments` port, tagged `phil_commerce.stock_commitments`; `DeferredSales`, which
  follows an order's payments, calls a balance by its trigger and the balances due in batches of at most 1,000, and
  says what is due and whether the goods may leave; `Domain\Contract\DeferredSaleRepository` and
  `DoctrineDeferredSaleRepository`, which lists the balances due through DBAL. The bundle requires
  `symfony/event-dispatcher-contracts`.
- `PaymentAttemptKey`, `PaymentAttemptPolicy`, `PaymentAttemptState`, `PaymentAttemptDecision`, `PaymentAttemptLimiter`;
  the `PaymentAttemptStateStore` port, under `Application\Port`.
- Receipts and credit notes: `Receipt`, `DocumentKind`, `Issuer`, `ReceiptRefused`; the `Receipts` service and the
  `phil_commerce.receipts` configuration, whose `receipt_prefix` and `credit_note_prefix` are refused when a prefix
  and `number_digits` exceed the 32 characters of a document number (`PhilCommerceBundle::DOCUMENT_NUMBER_LENGTH`),
  or when the two prefixes are equal, and whose `issuer.tax_numbers` codes are kept as written; the
  `DocumentNumbering` port and `DoctrineDocumentNumbering` on `phil_commerce_document_sequence`; `ReceiptRepository`
  and `DoctrineReceiptRepository` on `phil_commerce_receipt`. `Receipts::issue()` and `credit()` check again once
  the number is taken, under the series' lock, so two concurrent issues for one order give one receipt and a line is
  never credited twice. `DoctrineDocumentNumbering` reads the series' row again once locked, and creates a series'
  first row in a savepoint.
- The PDF invoice: `ReceiptPdf`, written by the native `Document` of `phil/paged-media`, which the bundle requires;
  Helvetica by default, or a TrueType font and its bold given by the application; PDF/A-2u for an archived receipt;
  the document's title, author and creation date are the receipt's. The minus sign, the narrow no-break space and the
  thin spaces print as their Windows-1252 equivalents, and a long description wraps below its line. The options of a
  configured line print below it, labels `option` and `to_draw`.
- The order's life cycle as the `phil_commerce_order` state machine of `symfony/workflow`, which the bundle requires:
  `OrderWorkflow`, `OrderMarkingStore`, the service `phil_commerce.order_workflow`, `OrderWorkflowPass`, which adds it
  to `workflow.registry`; `OrderLifecycle` (`quote()`, `place()`, `reopen()`, `cancel()`, `expire()`, `apply()`,
  `force()`, `changeStatus()`); the journal `OrderStateChange`, `Domain\Contract\OrderJournal` and
  `DoctrineOrderJournal` on `phil_commerce_order_state_change`; forced moves through the port
  `Application\Contract\TransitionForcing`, refused by `DenyForcing` by default, journalled with their reason and
  announced by `OrderStateForced`; `OrderCancelled`, dispatched by `OrderLifecycle` whenever an order ends cancelled —
  cancelled, expired or forced —, with its number, the state it left, its channel, its customer, whether it expired
  and who cancelled it; `Order::force()`, `OrderLocked::forcingRefused()` and `blocked()`; the application's
  statuses, `phil_commerce.order_statuses`, `Order::status()` and `changeStatus()`, cleared by each move,
  `OrderLocked::status()`.
- The customer of an order, `Order::customer()` and `assignCustomer()`, column `customer`, a stable identifier checked
  by `Identifier`, which refuses an email address.
- Gift options: `GiftOptions`, `Order::giftOptions()` and `offerAsGift()`, column `gift`;
  `OrderModifier::offerAsGift()`; `GiftWrapping`, the order processor of `phil_commerce.gift_wrapping`, which charges
  one `Fee` adjustment of origin `gift_wrap` or `gift_wrap:recycled` per wrapped order and refuses the options the
  store does not offer.
- Wish lists shared by a link where others buy: `WishList`, `WishListItem`, `Domain\Contract\WishListRepository`,
  `DoctrineWishListRepository` on `phil_commerce_wish_list`, `WishLists` (`create()`, `share()`, `shared()`,
  `buyFrom()`, `recordPurchase()`, listening to `OrderPurchased`), `Order::wishList()` and `buyFromWishList()`, column
  `wish_list`; `SecretToken`, 192 random bits shown once and stored as their SHA-256, which the bundle requires
  `ext-sodium` for.
- `OrderPurchased`, dispatched by `Payments` once per order when the payment that completes it is applied: the
  order's number, channel, customer and total.
- Manual discounts: `ManualDiscount` (`amount()`, `share()`), `Order::manualDiscounts()`, `grantManualDiscount()` and
  `withdrawManualDiscount()`, column `manual_discounts`; `OrderModifier::grantManualDiscount()` and
  `withdrawManualDiscount()`; `ManualDiscountProcessor`, tagged after the options and the catalogue's promotions and
  before the taxes, whose `Promotion` adjustments of origin `manual:<key>` share a discount on the whole order between
  its lines.
- Offline payments: `phil_commerce.offline_payments`, `Payment::declareOffline()`, `isOffline()`, `confirmReceived()`,
  `cancelOffline()` and `recordOfflineRefund()`, column `offline`; `Payments::declareOffline()`, `confirmOffline()`,
  `cancelOffline()` and `offlineMethods()`; `Payments::refund()` records an offline refund at once and
  `abandonStale()` leaves offline payments alone; `PaymentRefused::offline()` and `notOfflineMethod()`.
- Payment links that expire: `PaymentLink`, `Domain\Contract\PaymentLinkRepository`,
  `DoctrinePaymentLinkRepository` on `phil_commerce_payment_link`, `PaymentLinks` (`create()`, `open()`,
  `revokeAll()`), `phil_commerce.payment_links` (72 hours by default, 720 at most).
- Changes of placed orders, prepared, confirmed and versioned: `OrderChange`, `OrderChangeState`, `OrderChangeLine`,
  `OrderChangeRefused`, `Domain\Contract\OrderChangeRepository`, `DoctrineOrderChangeRepository` on
  `phil_commerce_order_change`, `OrderChanges` (`prepare()`, `confirm()`, `abandon()`); `Order::revision()` and
  `recordRevision()`, column `revision`.
- Expiry of unpaid orders: `Order::expire()` and `expiredAt()`, column `expired_at`; `OrderExpiry::sweep()` and
  `ExpirySweep`; `Domain\Contract\ExpiringOrders`, implemented by `DoctrineOrderRepository`, which deletes an expired
  order with its payments that captured nothing, its payment links, its changes and its journal;
  `phil_commerce.order_expiry` for orders of no channel the store knows.
- Refunds in two steps: `Refund`, `RefundState`, `Domain\Contract\RefundRepository`, `DoctrineRefundRepository` on
  `phil_commerce_refund`, `Refunds` (`grant()`, `execute()`, `withdraw()`), `phil_commerce.refunds.single_step`;
  `PaymentRefused::refundSettled()`; `OrderRefunded`, dispatched by `Refunds` once per refund executed, with the
  order's number, the refund's and the payment's identifiers, the order's channel and customer, and the amount.
- `Application\Contract\OrderShipments`, when an order or one of its lines left the store, aliased over
  `UnknownShipments`, which knows of none, for a logistics module or the application to replace.
- The document register: `DocumentType`, the ports `Application\Contract\DocumentTypes`, tagged
  `phil_commerce.document_types`, and `Application\Contract\DocumentSeries`, which `DoctrineDocumentNumbering`
  implements; `CoreDocumentTypes`, `DocumentRegister`, `phil_commerce.document_types`; two types never share a code or
  a prefix. Each type has a `Visibility` — listed among the buyer's documents, by direct link only, or kept for the
  store, the default —, receipts and credit notes listed; `DocumentRegister::listed()`, the key `visibility` of
  `phil_commerce.document_types`. The bundle requires `psr/clock`.
- The Flex recipe under `recipe/`: the bundle registered, `config/packages/phil_commerce.yaml` and the route of the
  webhook endpoint.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
