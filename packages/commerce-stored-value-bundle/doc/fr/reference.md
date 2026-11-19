<!-- phil-dev source-sha256=c9088ea8cba54f4ccc1ad43dc04872e1c6bceb3762aea136f436bff61433a0c0 -->
# Référence

Chaque type public du module.

| Type | Sorte | Rôle |
|---|---|---|
| `PhilCommerceStoredValueBundle` | `final class`, `AbstractBundle` | Alias `phil_commerce_stored_value` ; refuse de se charger sans `PhilCommerceBundle` |
| `Domain\Model\Instrument` | `final class`, entité Doctrine | Une carte-cadeau ou un crédit en magasin, son solde |
| `Domain\Model\Movement` | `final class`, entité Doctrine | Un changement d'un solde |
| `Domain\Instrument\InstrumentKind` | énumération | `GiftCard`, `StoreCredit` |
| `Domain\Instrument\GiftCardOrigin` | énumération | `Given`, `Paid` : si une carte-cadeau peut expirer |
| `Domain\Instrument\MovementKind` | énumération | `Issued`, `Loaded`, `Granted`, `Redeemed`, `Restored`, `Voided` |
| `Domain\ValueObject\GiftCardCode` | `final readonly class` | Un code : généré, lu, réduit à son empreinte |
| `Domain\Exception\StoredValueRefused` | `final class`, `DomainError` | Une opération refusée : inconnu, annulé, expiré, insuffisant, une commande non payée, un remboursement sans destination |
| `Domain\Repository\InstrumentRepository` | interface | Les instruments et les mouvements |
| `Application\GiftCards` | `final readonly class` | Émettre, recharger, annuler, trouver une carte ; annuler les cartes d'une commande annulée |
| `Application\Port\StoreCreditHolders` | interface | Le client d'une commande, pour un remboursement qu'une carte annulée ou expirée ne peut pas prendre |
| `Application\IssuedGiftCard` | `final readonly class` | Une carte tout juste émise, et son code |
| `Application\StoreCredits` | `final readonly class` | Accorder du crédit, les soldes |
| `Application\StoredValuePayments` | `final readonly class` | Payer avec une carte ou du crédit |
| `Application\GiftCardSellables` | `final readonly class`, le `SellableResolver` du noyau | Le type de vendable `gift_card` |
| `Application\StoredValueGateway` | `final readonly class`, le `PaymentGateway` du noyau | Les méthodes `gift_card` et `store_credit` |
| `Infrastructure\Doctrine\DoctrineStoredValueRepository` | `final readonly class` | Le dépôt sur le gestionnaire d'entités |
| `Infrastructure\Pdf\GiftCardPdf` | `final readonly class` | Une carte tout juste émise en PDF de la taille d'une carte de paiement |

Voir [scope.md](scope.md) pour ce qui n'est pas commencé.
