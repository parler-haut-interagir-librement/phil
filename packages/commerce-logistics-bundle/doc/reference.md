# Reference

Every public type of the module.

| Type | Kind | Role |
|---|---|---|
| `PhilCommerceLogisticsBundle` | `final class`, `AbstractBundle` | Alias `phil_commerce_logistics`; refuses to load without `PhilCommerceBundle` |
| `Domain\Model\Location` | `final class`, Doctrine entity | A place stock is kept, by priority |
| `Domain\Model\StockLevel` | `final class`, Doctrine entity | On hand and reserved of a SKU at a location |
| `Domain\Model\StockMovement` | `final class`, Doctrine entity | A line of the stock journal |
| `Domain\Model\Reservation` | `final class`, Doctrine entity | Units set aside for an order; of those shipped, how many came back |
| `Domain\Model\DeliveryMethod` | `final class`, Doctrine entity | A way an order reaches its buyer |
| `Domain\Model\Shipment` | `final class`, Doctrine entity | A parcel that left: lines, carrier, tracking |
| `Domain\Model\ShippingCost` | `final class`, Doctrine entity | What a shipment really cost: carrier, service, tracking, amount |
| `Domain\Model\StockPolicy` | `final class`, Doctrine entity | Whether a SKU sells without stock |
| `Domain\Model\Convention`, `ConventionQuantity` | `final class`, Doctrine entities | A convention and its days; the units of a SKU prepared for it |
| `Domain\Model\PrintJob` | `final class`, Doctrine entity | Units of an order sent to print, once per order and SKU |
| `Domain\Model\PurchaseOrder` | `final class`, Doctrine entity | Goods ordered from a supplier, what came, when the rest is expected, each line's unit cost and owner |
| `Domain\Purchasing\PurchaseLine` | `final readonly class` | One SKU of a purchase order: its units, and its unit cost and owner when given |
| `Domain\Model\CostLayer` | `final class`, Doctrine entity | Units of a SKU at a location that came in together and their value: a receipt, a pool, or an owner's units without value |
| `Domain\Model\CostEntry` | `final class`, Doctrine entity | A line of the cost journal: units, uncosted units, value, owner, reason |
| `Domain\Cost\CostingMethod`, `CostEntryKind`, `VarianceReason` | string-backed enums | `weighted_average` or `fifo`; why a value moved; why units on hand differ |
| `Domain\Cost\CostShare` | `final class` | A part of a value at its unit rate, rounded half up; a landed cost shared over lines by `Money::allocate()` |
| `Domain\ValueObject\ValuedStock`, `SaleCost` | `final readonly class` | The shop's costed units of a SKU at a location and their value; the cost of one sale's units of a SKU |
| `Domain\Event\UnitsShipped`, `ShippedLine` | `final readonly class`, the first a `DomainEvent` | Units of an order that left the shelf, per SKU and location |
| `Domain\Model\SerialNumber` | `final class`, Doctrine entity | A unit known by its serial number, where it is, the order it left with |
| `Domain\Model\ShippingDocument` | `final class`, Doctrine entity | The number a delivery note or a picking slip was issued under |
| `Domain\Model\ShippingWave`, `WaveAssignment` | `final class`, Doctrine entities | Orders planned to ship on an estimated day; an order in a wave |
| `Domain\Carrier\Parcel`, `ShippingAddress`, `RateRequest`, `LabelRequest` | `final readonly class` | What a carrier is asked to price or label |
| `Domain\Carrier\RateQuote`, `Label` | `final readonly class` | A carrier's rate for a service; a label, its tracking number, cost and document |
| `Domain\Carrier\ShippingMargin` | `final readonly class` | An order's delivery charged against its parcels' cost |
| `Domain\Delivery\FulfilmentState` | string-backed enum | `Unshipped`, `Partial`, `Shipped` |
| `Domain\Stock\MovementKind`, `Domain\Stock\ReservationState`, `Domain\Delivery\DeliveryKind` | string-backed enums | Why stock moved, `adjusted`, `backordered` and `returned` included; where a reservation stands; how an order is delivered |
| `Domain\Purchasing\PurchaseState`, `Domain\Serial\SerialState`, `Domain\Document\ShippingDocumentKind` | string-backed enums | Where a purchase order stands; where a serialised unit is; a delivery note or a picking slip |
| `Domain\Exception\StockRefused` | `final class`, `DomainError` | A movement, a serial number, a wave or a convention that cannot be as asked |
| `Domain\Exception\PurchaseRefused` | `final class`, `DomainError` | A purchase order, or a receipt against it, that cannot happen |
| `Domain\Exception\CarrierRefused` | `final class`, `DomainError` | A carrier unknown, refusing or silent; a cost recorded twice or in another currency |
| `Domain\Exception\CostRefused` | `final class`, `DomainError` | A cost, a layer, a landed cost or an owners' share that cannot be recorded |
| `Domain\Repository\LocationRepository`, `StockRepository`, `StockPolicyRepository`, `DeliveryMethodRepository`, `ShipmentRepository`, `ShippingCostRepository`, `ConventionRepository`, `PurchaseOrderRepository`, `SerialNumberRepository`, `ShippingDocumentRepository`, `ShippingWaveRepository` | interfaces | The module's storage |
| `Domain\Contract\CostRepository` | interface | Cost layers and the cost journal |
| `Application\Contract\InventoryValuation` | interface | The value of the stock, read without writing |
| `Application\Contract\StockOwnership` | interface | Whose units leave a level the shop and owners share |
| `Application\Port\CarrierGateway` | interface | A carrier's rates and labels; tagged `phil_commerce_logistics.carrier` |
| `Application\Port\PrintOnDemand` | interface | Units sent to print; tagged `phil_commerce_logistics.print_on_demand` |
| `Application\Stock` | `final readonly class` | Receiving, counts, adjustments with a reason, transfers, reservations, shipping them whole or in part, customer returns; announces `UnitsShipped` |
| `Application\Shipments` | `final readonly class` | Shipping an order in parcels, what is left, its fulfilment |
| `Application\DeliveryOptions` | `final readonly class` | The methods serving an order, the chosen one, its cost |
| `Application\ShippingProcessor` | `final readonly class`, the core's `OrderProcessor` | The delivery charge, after the promotions and before the taxes |
| `Application\ShippingLabels` | `final readonly class` | Rates of the carriers, labels, the cost of each shipment, an order's margin |
| `Application\ConventionStock` | `final readonly class` | Quantities per convention, what is left to print, the route of an order placed online |
| `Application\Purchasing` | `final readonly class` | Purchase orders and their receipts into stock, a landed cost shared over their lines |
| `Application\Costing` | `final readonly class` | The cost layers and journal kept in step with each movement, by the configured method |
| `Application\InventoryValue` | `final readonly class`, `InventoryValuation` | The value of the stock from the cost layers and journal |
| `Application\ShopUnitsFirst` | `final readonly class`, `StockOwnership` | The shop's units leave first, then each owner's in the order they came |
| `Application\SerialNumbers` | `final readonly class` | Serial numbers registered, moved, shipped and put back |
| `Application\ShippingDocuments` | `final readonly class` | Delivery notes and picking slips, numbered by the core's register |
| `Application\Document\DeliveryNote`, `DeliveryNoteLine`, `PickingSlip`, `PickingLine` | `final readonly class` | What the two documents print |
| `Application\LogisticsDocumentTypes` | `final readonly class`, the core's `DocumentTypes` | The series of the two documents |
| `Application\ShippingWaves` | `final readonly class` | Shipping waves: planned, assigned, rescheduled, released |
| `Application\DeferredStock` | `final readonly class`, the core's `StockCommitments` | The stock of a sale paid later, reserved and released |
| `Infrastructure\Doctrine\DoctrineLogisticsRepository` | `final readonly class` | The twelve repositories on the entity manager |
| `Infrastructure\CanadaPost\CanadaPostCarrier` | `final class`, `CarrierGateway`, `ResetInterface` | Canada Post's REST API in JSON |
| `Infrastructure\CanadaPost\CanadaPostCredentials` | `final readonly class` | The shop's key, secret, customer number, contract and addresses |
| `Infrastructure\CanadaPost\CanadaPostPayloads` | `final class` | The JSON bodies sent to Canada Post and read back |

See [scope.md](scope.md) for what lies outside the module.
