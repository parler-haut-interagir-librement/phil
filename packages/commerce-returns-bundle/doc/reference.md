# Reference

Every public type of the module.

| Type | Kind | Role |
|---|---|---|
| `PhilCommerceReturnsBundle` | `final class`, `AbstractBundle` | Alias `phil_commerce_returns`; refuses to load without `PhilCommerceBundle` |
| `Domain\Model\ReturnRequest` | `final class`, Doctrine entity | A return, its lines, its steps, the specials it breaks, the delivery it gives back, its label and slip number, its value |
| `Domain\Model\ReturnCreditNote` | `final class`, Doctrine entity | The credit note of a return |
| `Domain\ValueObject\ReturnLine` | `final readonly class` | One line of a return |
| `Domain\ValueObject\ReturnPolicy` | `final readonly class` | The window and where it counts from, the restocking fee, the delivery refund, the expiry |
| `Domain\ValueObject\ReturnLabel` | `final readonly class` | A carrier's label: carrier, tracking number, file |
| `Domain\ValueObject\ReturnSlip` | `final readonly class` | What a return slip says |
| `Domain\Event\ReturnStateChanged`, `ReturnSettled`, `ReturnCreditNoteIssued` | `final readonly class`, `DomainEvent` | A step, a settlement, a credit note |
| `Domain\Rma\ReturnState`, `ReturnReason`, `ItemCondition`, `Resolution`, `DeliveryRefund` | enums | States, reasons, conditions, resolutions, when the delivery comes back |
| `Domain\Exception\ReturnRefused` | `final class`, `DomainError` | A return refused, or a step it cannot take |
| `Domain\Repository\ReturnRepository` | interface | Returns, the authorized ones waiting since a moment, credit notes |
| `Application\Returns` | `final readonly class` | Requesting, the steps, settling, expiry, labels, anonymization |
| `Application\ReturnValuation` | `final readonly class` | What a request takes back and is worth: lines, sets, specials, delivery |
| `Application\ReturnCreditNotes` | `final readonly class` | Issues the credit note of a return |
| `Application\ReturnSlips` | `final readonly class` | The slip of an authorized return, numbered, and its PDF |
| `Application\ReturnDocumentTypes` | `final readonly class`, the core's `DocumentTypes` | The series `return_slip` |
| `Application\Settlement` | `final readonly class` | How a return was settled |
| `Application\ReturnsGateway` | `final readonly class`, the core's `PaymentGateway` | The method `exchange` |
| `Application\Port\Restocking` | interface | Puts units back on the shelf; autoconfigured |
| `Application\Port\StoreCreditGranter` | interface | Grants store credit |
| `Application\Contract\SetParts`, `SpecialDiscounts`, `ShipmentDates` | interfaces | The parts of a set, the specials, an order's last shipment |
| `Application\Contract\ReturnLabels`, `ReturnSlipRenderer` | interfaces | A carrier's label, a slip as a PDF |
| `Infrastructure\Doctrine\DoctrineReturnRepository` | `final readonly class` | The repository on the entity manager |
| `Infrastructure\StoredValue\StoredValueStoreCredit` | `final readonly class` | Store credit through `phil/commerce-stored-value-bundle` |
| `Infrastructure\Catalog\CatalogSetParts` | `final readonly class` | Sets through `phil/commerce-catalog-bundle` |
| `Infrastructure\Promotions\PromotionSpecials` | `final readonly class` | Specials through `phil/commerce-promotions-bundle` |
| `Infrastructure\Logistics\LogisticsShipmentDates` | `final readonly class` | Shipment dates through `phil/commerce-logistics-bundle` |
| `Infrastructure\Logistics\LogisticsRestocking` | `final readonly class`, `Restocking` | Resellable units back in the stock of `phil/commerce-logistics-bundle`, at the cost they left at |
| `Infrastructure\PagedMedia\PagedMediaReturnSlips` | `final readonly class` | Slips drawn with `phil/paged-media` |

See [scope.md](scope.md) for what is not started.
