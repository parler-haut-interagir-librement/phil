<!-- phil-dev source-sha256=6dcab44ddc6f4b879b4eac2446c84467064ee145df7a4e88ce5c31f5d5b5ed95 -->
# Référence

Chaque type public du module.

| Type | Sorte | Rôle |
|---|---|---|
| `PhilCommercePosBundle` | `final class`, `AbstractBundle` | Alias `phil_commerce_pos` ; refuse de se charger sans `PhilCommerceBundle` |
| `Domain\Model\Register` | `final class`, entité Doctrine | Un tiroir-caisse |
| `Domain\Register\RegisterRole` | énumération | `Main` ou `Mobile` |
| `Domain\Model\Terminal` | `final class`, entité Doctrine | Un terminal connecté à une caisse |
| `Domain\Model\TerminalPayment` | `final class`, entité Doctrine | Un paiement présenté sur un terminal, et la référence du processeur |
| `Domain\Model\SalePreset` | `final class`, entité Doctrine | Un préréglage de vente posé sur les canaux du noyau |
| `Domain\Model\CashierPin` | `final class`, entité Doctrine | Le NIP d'un caissier, sous forme d'empreinte |
| `Domain\Model\CashSession` | `final class`, entité Doctrine | Un quart et son tiroir |
| `Domain\Model\CashMovement` | `final class`, entité Doctrine | De l'argent comptant qui entre ou qui sort, et son arrondi |
| `Domain\Model\RegisterSale` | `final class`, entité Doctrine | Une commande enregistrée dans une session |
| `Domain\Model\SessionPayment` | `final class`, entité Doctrine | Un paiement reçu dans une session |
| `Domain\Model\HeldSale` | `final class`, entité Doctrine | Un panier mis en attente |
| `Domain\Model\Layaway` | `final class`, entité Doctrine | De la marchandise mise de côté contre un acompte |
| `Domain\Exception\OfflineSaleForbidden` | `final class`, `DomainError` | Une vente hors ligne que la porte a refusée |
| `Domain\Model\ImportedSale` | `final class`, entité Doctrine | Une vente hors ligne importée, et son écart |
| `Domain\Session\SessionState`, `CashMovementKind`, `Domain\Layaway\LayawayState` | énumérations | Les états et les sortes |
| `Domain\ValueObject\CashRounding` | `final readonly class` | L'arrondi de l'argent comptant à un incrément |
| `Domain\Exception\PosRefused` | `final class`, `DomainError` | Une opération que la caisse a refusée |
| `Domain\Exception\CashierPinRefused` | `final class`, `DomainError` | Un NIP refusé : changement désactivé, mauvais NIP, trop de tentatives (`retryAfter`) |
| `Domain\Repository\RegisterRepository`, `CashSessionRepository`, `RegisterSaleRepository` | interfaces | Le stockage |
| `Application\Registers` | `final readonly class` | Créer et trouver des caisses et leurs terminaux, une caisse principale par lieu |
| `Application\CashSessions` | `final readonly class` | L'ouverture, les mouvements d'argent comptant, la fermeture, les rapports |
| `Application\SessionReport` | `final readonly class` | Le rapport de fin de journée |
| `Application\RegisterCheckout` | `final readonly class` | La mise en attente et la reprise, le pourboire, l'enregistrement de la vente sous un préréglage |
| `Application\RegisterPayments` | `final readonly class` | L'argent comptant, les méthodes enregistrées, le comptage, les remboursements |
| `Application\CashTender` | `final readonly class` | L'argent comptant reçu : dû, remis, monnaie rendue |
| `Application\RegisterGateway` | `final readonly class`, le `PaymentGateway` du noyau | `cash` et les méthodes enregistrées |
| `Application\Layaways` | `final readonly class` | Les acomptes, l'ouverture, l'achèvement, l'annulation |
| `Application\OfflineSale` | `final readonly class` | Une vente hors ligne, lue |
| `Application\OfflineSales` | `final readonly class` | L'importation des ventes hors ligne |
| `Application\Contract\OfflineSaleGate` | interface | Si la personne qui envoie une vente hors ligne peut l'importer |
| `Application\Contract\PaymentTerminal` | interface | Un adaptateur des terminaux connectés d'un processeur |
| `Application\Contract\TillDrawForm` | interface | Le formulaire qui saisit au comptoir les tirages d'un tireur |
| `Application\TerminalPayments` | `final readonly class` | Les débits, les annulations et les notifications des processeurs |
| `Application\TerminalGateway` | `final readonly class`, le `PaymentGateway` du noyau | La méthode des terminaux connectés |
| `Application\TerminalAdapters` | `final readonly class` | Les adaptateurs, par nom ou par notification |
| `Application\TerminalCharge` | `final readonly class` | Ce qu'on demande à un terminal de débiter |
| `Application\SalePresets` | `final readonly class` | Créer, lister et appliquer les préréglages |
| `Application\CustomerDisplay`, `DisplayedCart` | `final readonly class` | Ce que montre l'afficheur client |
| `Application\TillDraws`, `PendingDraw` | `final readonly class` | Les tirages saisis au comptoir |
| `Application\CashDifferences`, `CashDifferenceReport` | `final readonly class` | Le rapport des écarts de caisse |
| `Application\CashierSwitch` | `final readonly class` | Le changement de caissier par NIP |
| `Infrastructure\Doctrine\DoctrinePosRepository` | `final readonly class` | Chaque dépôt sur le gestionnaire d'entités |
| `Infrastructure\Http\OfflineSaleController` | `final readonly class` | L'endroit où les caisses envoient les ventes hors ligne |
| `Infrastructure\Http\TerminalNotificationController` | `final readonly class` | L'endroit où les processeurs envoient leurs notifications |
| `Infrastructure\Terminal\FakePaymentTerminal` | `final class`, `PaymentTerminal` | L'adaptateur factice, `fake` |
| `assets/src/offline_queue.js` | module JavaScript | `OfflineQueue`, `IndexedDbStore`, `newSaleId()` |
| `assets/src/offline_queue_controller.js` | contrôleur Stimulus | `offline-queue` |

Voir [scope.md](scope.md) pour ce qui n'est pas commencé.
