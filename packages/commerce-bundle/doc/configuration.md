# Configuration reference

The extension alias is `phil_commerce`. Its keys are the table of tax rates, the receipts, the document types, the
order statuses, the gift wrapping, the offline payments, the payment links, the expiry of unpaid orders and the
refunds.

```yaml
# config/packages/phil_commerce.yaml
phil_commerce:
    tax_rates:
        goods:                       # a tax category, as the sellables name it
            QC: { GST: '5', QST: '9.975' }   # a zone, as the orders name it: rate codes and their percentages
            ON: { HST: '13' }
        digital:
            QC: { GST: '5', QST: '9.975' }
```

| Key | Type | Default | Effect |
|---|---|---|---|
| `tax_rates` | map: category → zone → code → percentage | `{}` | The rates `ConfiguredTaxRates` gives `TaxOrderProcessor`; a category or a zone that is absent has no tax |
| `receipts.enabled` | bool | `false`, `true` once the block is set | Registers `Receipts` |
| `receipts.issuer.name` | string | — | Required when receipts are enabled |
| `receipts.issuer.address` | list of strings | `[]` | The lines of the issuer's address |
| `receipts.issuer.tax_numbers` | map: rate code → registration number | `{}` | Shown on each receipt |
| `receipts.receipt_prefix`, `receipts.credit_note_prefix` | string | `R-`, `CN-` | The prefix of each series; the two differ, and each with `number_digits` holds in 32 characters |
| `receipts.number_digits` | int, 1 to 18 | `8` | The sequence is padded with zeros to this many digits |
| `document_types` | map: code → `{prefix, digits, visibility}` | `{}` | The application's document types in the register; `digits` 8 by default, `visibility` `hidden` by default, or `listed`, `unlisted` ([documents.md](documents.md#visibility)) |
| `order_statuses` | map: status → `cart`, `quoted`, `placed` or `cancelled` | `{}` | The application's statuses, each attached to a state ([lifecycle.md](lifecycle.md#statuses)) |
| `gift_wrapping.enabled` | bool | `false` | Whether the store wraps gifts ([gifts.md](gifts.md)) |
| `gift_wrapping.recycled_option` | bool | `true` | Whether it offers the recycled wrap |
| `gift_wrapping.label` | string | `Gift wrapping` | The label of the wrapping's adjustment |
| `gift_wrapping.prices`, `gift_wrapping.recycled_prices` | map: currency → decimal | `{}` | What each wrapping costs; free without a price, the recycled one at the plain price without its own |
| `offline_payments` | list of methods | `[]` | The methods paid offline and confirmed by hand ([payments.md](payments.md#offline-payments)) |
| `payment_links.default_hours`, `payment_links.max_hours` | int | `72`, `720` | How long a payment link lasts by default, and at most ([payment-links.md](payment-links.md)) |
| `order_expiry.unpaid_hours`, `order_expiry.delete_after_days` | int or `null` | `24`, `30` | For orders of no channel the store knows: when an unpaid order expires, when an expired one is deleted ([expiry.md](expiry.md)) |
| `refunds.single_step` | bool | `false` | Whether granting a refund executes it at once ([refunds.md](refunds.md)) |

- **Categories, zones, rate codes and the codes of `tax_numbers` are kept as written**: `digital-goods`, `CA-QC`
  and `QC-TVQ` keep their dashes, so they match the tax categories of the sellables, the tax zones of the orders
  and the rate codes printed on receipts.
- **The numbers of a series fit their column**: a prefix and `number_digits` take 32 characters at most, the
  length of a receipt's number, and the two prefixes differ, so that the two series never give the same number.
  Either mistake fails the container build.
- **Percentages are decimal strings**, read by `TaxRate::ofPercent()` of `phil/tax`: up to four decimals, from 0
  to 100. A percentage a rate cannot hold, or a code that is not one, fails the container build.
- **A document type, a wrapping price or a status the bundle cannot use fails the container build**: a code or a
  prefix two types share, a price its currency cannot hold, a status of no state, a link lasting longer by default
  than at most, an offline method that is not a method name.
- **An application with an external tax engine** aliases its own `TaxRateResolver` instead; the table is then
  unused ([extension-points.md](extension-points.md)).

## What the bundle registers

| Service | Role |
|---|---|
| `Phil\CommerceBundle\Application\OrderModifier` (public) | Changes an order through the four tagged ports it reads and `LineOptions`, and recomputes it |
| `Phil\CommerceBundle\Application\LineOptions` | The options of what is sold, from the services tagged `phil_commerce.sellable_options` |
| `Phil\CommerceBundle\Application\OptionSupplementProcessor` | Charges what the options add; tagged `phil_commerce.order_processor`, before `TaxOrderProcessor` |
| `Phil\CommerceBundle\Application\OptionDraws` (public) | Draws the options of a paid order through the services tagged `phil_commerce.option_drawer` |
| `Phil\CommerceBundle\Application\ConfiguredTaxRates` | The rates of `tax_rates`; aliased as `TaxRateResolver` |
| `Phil\CommerceBundle\Application\TaxOrderProcessor` | Taxes each line; tagged `phil_commerce.order_processor` with no position, the other processors naming it in their `before` or `after` |
| `Phil\CommerceBundle\Application\Channels` (public) | With DoctrineBundle only: the channel of a request, the orders a channel opens |
| `Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineChannelRepository`, aliased (public) as `ChannelRepository` | With DoctrineBundle only: stores channels |
| `Phil\CommerceBundle\Application\Payments` (public) | With DoctrineBundle only: payments through the gateways tagged `phil_commerce.payment_gateway` |
| `Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineOrderRepository`, aliased (public) as `OrderRepository` | With DoctrineBundle only: stores orders through the default entity manager |
| `Phil\CommerceBundle\Infrastructure\Doctrine\DoctrinePaymentRepository`, aliased (public) as `PaymentRepository` | With DoctrineBundle only: stores payments |
| `Phil\CommerceBundle\Application\DeferredSales` (public) | With DoctrineBundle only: deferred sales, through the services tagged `phil_commerce.stock_commitments` and the event dispatcher when there is one ([deferred-sales.md](deferred-sales.md)) |
| `Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineDeferredSaleRepository`, aliased (public) as `DeferredSaleRepository` | With DoctrineBundle only: stores deferred sales |
| `Phil\CommerceBundle\Application\Receipts` (public) | With DoctrineBundle and `receipts` enabled: issues receipts and credit notes |
| `Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineDocumentNumbering`, aliased as `DocumentNumbering` and `DocumentSeries` | With DoctrineBundle only: a locked row per series |
| `Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineReceiptRepository`, aliased (public) as `ReceiptRepository` | With DoctrineBundle only: stores receipts and credit notes |
| `Phil\CommerceBundle\Application\ManualDiscountProcessor` | Takes the discounts granted by hand off; tagged `phil_commerce.order_processor`, after the options and the catalogue's promotions, before `TaxOrderProcessor` |
| `Phil\CommerceBundle\Application\GiftWrapping` (public) | The wrapping of `gift_wrapping`; tagged `phil_commerce.order_processor` |
| `phil_commerce.order_workflow` (public) | The `phil_commerce_order` state machine, on `OrderMarkingStore` and the event dispatcher when there is one; added to `workflow.registry` when there is one |
| `Phil\CommerceBundle\Application\OrderLifecycle` (public) | Moves, journals and forces orders, sets their statuses, announces `OrderStateForced` and `OrderCancelled` through the event dispatcher when there is one |
| `Phil\CommerceBundle\Application\DenyForcing`, aliased as `TransitionForcing` | Nobody forces an order until the application aliases its own |
| `Phil\CommerceBundle\Application\UnknownShipments`, aliased (public) as `OrderShipments` | No order is known to have left until a logistics module or the application aliases its own |
| `Phil\CommerceBundle\Application\CoreDocumentTypes` | The receipts, the credit notes and `document_types`; tagged `phil_commerce.document_types` |
| `Phil\CommerceBundle\Application\DocumentRegister` (public) | The register of document types, numbered through `DocumentSeries` |
| `Phil\CommerceBundle\Application\WishLists` (public) | With DoctrineBundle only: wish lists; listens to `OrderPurchased` |
| `Phil\CommerceBundle\Application\PaymentLinks` (public) | With DoctrineBundle only: payment links |
| `Phil\CommerceBundle\Application\OrderChanges` (public) | With DoctrineBundle only: changes of placed orders |
| `Phil\CommerceBundle\Application\OrderExpiry` (public) | With DoctrineBundle only: the sweep of unpaid orders |
| `Phil\CommerceBundle\Application\Refunds` (public) | With DoctrineBundle only: refunds in two steps, announced by `OrderRefunded` through the event dispatcher when there is one |
| `DoctrineWishListRepository`, `DoctrinePaymentLinkRepository`, `DoctrineOrderChangeRepository`, `DoctrineOrderJournal`, `DoctrineRefundRepository`, each aliased (public) as its port | With DoctrineBundle only: store wish lists, payment links, changes, the journal and refunds |
| `Phil\CommerceBundle\Infrastructure\Http\PaymentWebhookController` (public) | With DoctrineBundle only: receives the processors' notifications at the route the application gives it |

`Payments` and `Receipts` need the repositories and the numbering Doctrine provides: an application that stores
payments and receipts otherwise declares the two services itself, with its own adapters of `PaymentRepository`,
`ReceiptRepository` and `DocumentNumbering`.

With DoctrineBundle, the bundle also prepends the column types `phil_commerce_order_lines` and
`phil_commerce_adjustments`, the attribute mapping of `Domain\Model` ([orders.md](orders.md#storage)) and that of
the document sequences.

Autoconfiguration tags every service implementing `SellableResolver`, `UnitPriceResolver`, `OrderProcessor`,
`PaymentGateway`, `SellableAvailability`, `SellableOptions`, `OptionDrawer`, `StockCommitments` or `DocumentTypes`;
unit price resolvers and order processors take their place with `before` and `after` on their tag, naming the services
they come before or after ([extension-points.md](extension-points.md)).

## What the application decides and wires itself

| Decision | Where it goes |
|---|---|
| The modules it sells with | The module packages it installs and registers ([modules.md](modules.md)) |
| The currencies it accepts | `phil_money.currencies`, with `phil/money-bundle` ([money.md](money.md)) |
| What else it sells | A `SellableResolver` of its own ([extension-points.md](extension-points.md)) |
| The options of what it sells, and who draws them | A `SellableOptions` and its `OptionDrawer`s, unless a module declares them ([options.md](options.md)) |
| When the draws of a paid order are made | A call to `OptionDraws::draw()` once its payments are `Paid` ([options.md](options.md#draws-after-payment)) |
| The tax rates of a sale | `phil_commerce.tax_rates`, or its own `TaxRateResolver` ([taxes.md](taxes.md)) |
| The limiter's budget, penalty step and window | `PaymentAttemptPolicy::of()`, from parameters ([payment-attempts.md](payment-attempts.md)) |
| The IPv6 prefix that makes one client | The second argument of `PaymentAttemptKey::forClientIp()`, 64 by default |
| Who issues its receipts, and how their numbers read | `phil_commerce.receipts` ([receipts.md](receipts.md)) |
| Which processors it uses | A `PaymentGateway` per processor, and the route of the webhook endpoint ([payments.md](payments.md)) |
| Where limiter states are stored | An adapter of `PaymentAttemptStateStore` |
| Who may force an order outside its transitions | Its own `TransitionForcing`, aliased over `DenyForcing` ([lifecycle.md](lifecycle.md#forcing-a-move)) |
| Who may grant a discount, grant or execute a refund | Its security voters, before calling `OrderModifier` or `Refunds` |
| When unpaid orders expire | `OrderExpiry::sweep()` on a schedule, and each channel's delays ([expiry.md](expiry.md)) |
| Who hears that an order is paid, refunded or cancelled | Listeners of `OrderPurchased`, `OrderRefunded` and `OrderCancelled` ([extension-points.md](extension-points.md#events)) |
| When an order left the store | Its own `OrderShipments`, or a logistics module's, aliased over `UnknownShipments` ([extension-points.md](extension-points.md#ordershipments)) |
| Who counts the robot verdicts seen at payment | The `VerdictHandler` of `phil/bot-detection-bundle`, with a counter wired for payment ([payment-attempts.md](payment-attempts.md)) |
| Whether a robot verdict forces a stronger authentication challenge or adds a penalty | Its payment flow |
