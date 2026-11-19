# Reference

Every public type of the module.

| Type | Kind | Role |
|---|---|---|
| `PhilCommerceSyndicationBundle` | `final class`, `AbstractBundle` | Alias `phil_commerce_syndication`; refuses to load without `PhilCommerceBundle` |
| `Domain\Model\Partner` | `final class`, Doctrine entity | A partner application: role, endpoint, key, the SKUs it is fed and its share |
| `Domain\Partner\PartnerRole` | enum | `Supplier`, `Reseller` |
| `Domain\Model\OutgoingMessage` | `final class`, Doctrine entity | A message in the outbox, its attempts and its envelope |
| `Domain\Model\ReceivedMessage` | `final class`, Doctrine entity | The id of a message already applied, per partner |
| `Domain\Model\PublishedPrice` | `final class`, Doctrine entity | A price a supplier published to a reseller |
| `Domain\Model\FedSku` | `final class`, Doctrine entity | One SKU a reseller is fed: the index a product update finds its resellers with |
| `Domain\Model\SyndicatedProduct` | `final class`, Doctrine entity | A supplier's product as its last message described it |
| `Domain\ValueObject\FeedItem` | `final readonly class` | A product as a supplier describes it to a reseller |
| `Domain\Model\LedgerEntry` | `final class`, Doctrine entity | What is owed between two partners for one order and its lines, or credited for one of its returns |
| `Domain\Model\PartnerShipment` | `final class`, Doctrine entity | A parcel of a forwarded order: SKUs, carrier, tracking |
| `Domain\Model\PartnerReturn` | `final class`, Doctrine entity | Goods sent back to the supplier: announced, received, credited |
| `Domain\Tracking\ReturnState` | enum | `Requested`, `Received` |
| `Domain\Exception\SyndicationRefused` | `final class`, `DomainError` | A message or an order refused |
| `Domain\Exception\UnknownPartner` | `final class`, `DomainError` | No active partner by that code |
| `Domain\Repository\PartnerRepository`, `MessageRepository`, `SyndicatedProductRepository`, `LedgerRepository`, `PublishedPriceRepository`, `PartnerShipmentRepository`, `PartnerReturnRepository` | interfaces | The storage of each |
| `Application\Partners` | `final readonly class` | Registering and feeding partners |
| `Application\Publisher` | `final readonly class` | Queuing `product.updated` for the resellers |
| `Application\Forwarder` | `final readonly class` | Queuing `order.forwarded` for the suppliers, and the ledger |
| `Application\Inbox` | `final readonly class` | Verifying and applying a partner's message |
| `Application\Courier` | `final readonly class` | Signing and delivering the outbox |
| `Application\Ledger` | `final readonly class` | Balances and settlement |
| `Application\MessageRetention` | `final readonly class` | Deleting the messages past their retention |
| `Application\ShipmentTracking` | `final readonly class` | `shipment.sent`, sent and received |
| `Application\PartnerReturns` | `final readonly class` | `return.requested` and `return.received`, and the credit in the ledger |
| `Application\MessageData` | `final readonly class` | Reads the data of a received message field by field |
| `Application\SyndicatedSellables` | `final readonly class`, the core's `SellableResolver` and `SellableAvailability` | The sellable type `syndicated` |
| `Application\PartnerKey` | `final readonly class`, `VerificationKeyProvider` | A partner's one key |
| `Application\ForwardedOrder` | `final readonly class` | An order a reseller forwarded |
| `Application\Port\MessageTransport` | interface | Carries a signed message |
| `Application\Port\ForwardedOrderHandler` | interface | Handles a forwarded order; autoconfigured |
| `Application\Port\PartnerShipmentHandler`, `PartnerReturnHandler` | interfaces | Handle a parcel; a return requested or received; autoconfigured |
| `Infrastructure\Doctrine\DoctrineSyndicationRepository` | `final readonly class` | Every repository on the entity manager |
| `Infrastructure\Doctrine\FedSkuIndex` | `final readonly class`, Doctrine `onFlush` listener | Keeps the `FedSku` rows in step with each partner's list of SKUs |
| `Infrastructure\Http\SyndicationWebhookController` | `final readonly class` | Where partners post |
| `Infrastructure\Http\HttpClientTransport` | `final readonly class` | The transport over Symfony's HTTP client |
| `Infrastructure\Console\DeliverMessagesCommand` | `final class` | `phil:commerce-syndication:deliver` |
| `Infrastructure\Console\PurgeMessagesCommand` | `final class` | `phil:commerce-syndication:purge` |

See [scope.md](scope.md) for what is not started.
