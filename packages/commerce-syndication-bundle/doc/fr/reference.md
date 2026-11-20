<!-- phil-dev source-sha256=dc0036c1f9c786b03081fb8b36c3dbc7395942485d7d7bbb9e09e8cdfd2a8057 -->
<!-- phil-dev source-sha256=0 -->
# Référence

Chaque type public du module.

| Type | Sorte | Rôle |
|---|---|---|
| `PhilCommerceSyndicationBundle` | `final class`, `AbstractBundle` | Alias `phil_commerce_syndication` ; refuse de se charger sans `PhilCommerceBundle` |
| `Domain\Model\Partner` | `final class`, entité Doctrine | Une application partenaire : rôle, point de terminaison, clé, les UGS qui lui sont fournies et sa part |
| `Domain\Partner\PartnerRole` | énumération | `Supplier`, `Reseller` |
| `Domain\Model\OutgoingMessage` | `final class`, entité Doctrine | Un message dans la boîte d'envoi, ses tentatives et son enveloppe |
| `Domain\Model\ReceivedMessage` | `final class`, entité Doctrine | L'identifiant d'un message déjà appliqué, par partenaire |
| `Domain\Model\PublishedPrice` | `final class`, entité Doctrine | Un prix qu'un fournisseur a publié à un revendeur |
| `Domain\Model\FedSku` | `final class`, entité Doctrine | Une UGS fournie à un revendeur : l'index par lequel une mise à jour de produit trouve ses revendeurs |
| `Domain\Model\SyndicatedProduct` | `final class`, entité Doctrine | Le produit d'un fournisseur tel que l'a décrit son dernier message |
| `Domain\ValueObject\FeedItem` | `final readonly class` | Un produit tel qu'un fournisseur le décrit à un revendeur |
| `Domain\Model\LedgerEntry` | `final class`, entité Doctrine | Ce qui est dû entre deux partenaires pour une commande et ses lignes, ou crédité pour un de ses retours |
| `Domain\Model\PartnerShipment` | `final class`, entité Doctrine | Un colis d'une commande transmise : UGS, transporteur, suivi |
| `Domain\Model\PartnerReturn` | `final class`, entité Doctrine | Des marchandises renvoyées au fournisseur : annoncées, reçues, créditées |
| `Domain\Tracking\ReturnState` | énumération | `Requested`, `Received` |
| `Domain\Exception\SyndicationRefused` | `final class`, `DomainError` | Un message ou une commande refusé |
| `Domain\Exception\UnknownPartner` | `final class`, `DomainError` | Aucun partenaire actif de ce code |
| `Domain\Repository\PartnerRepository`, `MessageRepository`, `SyndicatedProductRepository`, `LedgerRepository`, `PublishedPriceRepository`, `PartnerShipmentRepository`, `PartnerReturnRepository` | interfaces | Le stockage de chacun |
| `Application\Partners` | `final readonly class` | L'enregistrement et l'alimentation des partenaires |
| `Application\Publisher` | `final readonly class` | La mise en file de `product.updated` pour les revendeurs |
| `Application\Forwarder` | `final readonly class` | La mise en file de `order.forwarded` pour les fournisseurs, et le registre |
| `Application\Inbox` | `final readonly class` | La vérification et l'application du message d'un partenaire |
| `Application\Courier` | `final readonly class` | La signature et la livraison de la boîte d'envoi |
| `Application\Ledger` | `final readonly class` | Les soldes et le règlement |
| `Application\MessageRetention` | `final readonly class` | La suppression des messages dont la durée de conservation est dépassée |
| `Application\ShipmentTracking` | `final readonly class` | `shipment.sent`, envoyé et reçu |
| `Application\PartnerReturns` | `final readonly class` | `return.requested` et `return.received`, et le crédit dans le registre |
| `Application\MessageData` | `final readonly class` | Lit champ par champ les données d'un message reçu |
| `Application\SyndicatedSellables` | `final readonly class`, le `SellableResolver` et le `SellableAvailability` du noyau | Le type de vendable `syndicated` |
| `Application\PartnerKey` | `final readonly class`, `VerificationKeyProvider` | La clé unique d'un partenaire |
| `Application\ForwardedOrder` | `final readonly class` | Une commande qu'un revendeur a transmise |
| `Application\Port\MessageTransport` | interface | Transporte un message signé |
| `Application\Port\ForwardedOrderHandler` | interface | Traite une commande transmise ; autoconfigurée |
| `Application\Port\PartnerShipmentHandler`, `PartnerReturnHandler` | interfaces | Traitent un colis ; un retour demandé ou reçu ; autoconfigurées |
| `Infrastructure\Doctrine\DoctrineSyndicationRepository` | `final readonly class` | Chaque dépôt sur le gestionnaire d'entités |
| `Infrastructure\Doctrine\FedSkuIndex` | `final readonly class`, écouteur Doctrine `onFlush` | Garde les rangées `FedSku` au diapason de la liste d'UGS de chaque partenaire |
| `Infrastructure\Http\SyndicationWebhookController` | `final readonly class` | L'endroit où publient les partenaires |
| `Infrastructure\Http\HttpClientTransport` | `final readonly class` | Le transport par le client HTTP de Symfony |
| `Infrastructure\Console\DeliverMessagesCommand` | `final class` | `phil:commerce-syndication:deliver` |
| `Infrastructure\Console\PurgeMessagesCommand` | `final class` | `phil:commerce-syndication:purge` |

Voir [scope.md](scope.md) pour ce qui n'est pas commencé.
