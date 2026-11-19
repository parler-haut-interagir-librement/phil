# Reference

Every public type of the module.

| Type | Kind | Role |
|---|---|---|
| `PhilCommercePosBundle` | `final class`, `AbstractBundle` | Alias `phil_commerce_pos`; refuses to load without `PhilCommerceBundle` |
| `Domain\Model\Register` | `final class`, Doctrine entity | A till |
| `Domain\Register\RegisterRole` | enum | `Main` or `Mobile` |
| `Domain\Model\Terminal` | `final class`, Doctrine entity | A connected terminal at a register |
| `Domain\Model\TerminalPayment` | `final class`, Doctrine entity | A payment presented on a terminal, and the processor's reference |
| `Domain\Model\SalePreset` | `final class`, Doctrine entity | A sale preset over the core's channels |
| `Domain\Model\CashierPin` | `final class`, Doctrine entity | A cashier's PIN, as a hash |
| `Domain\Model\CashSession` | `final class`, Doctrine entity | A shift and its drawer |
| `Domain\Model\CashMovement` | `final class`, Doctrine entity | Cash in or out, and its rounding |
| `Domain\Model\RegisterSale` | `final class`, Doctrine entity | An order rung up in a session |
| `Domain\Model\SessionPayment` | `final class`, Doctrine entity | A payment taken in a session |
| `Domain\Model\HeldSale` | `final class`, Doctrine entity | A parked cart |
| `Domain\Model\Layaway` | `final class`, Doctrine entity | Goods put aside on a deposit |
| `Domain\Exception\OfflineSaleForbidden` | `final class`, `DomainError` | An offline sale the gate refused |
| `Domain\Model\ImportedSale` | `final class`, Doctrine entity | An offline sale imported, and its discrepancy |
| `Domain\Session\SessionState`, `CashMovementKind`, `Domain\Layaway\LayawayState` | enums | States and kinds |
| `Domain\ValueObject\CashRounding` | `final readonly class` | Rounding cash to an increment |
| `Domain\Exception\PosRefused` | `final class`, `DomainError` | An operation the register refused |
| `Domain\Exception\CashierPinRefused` | `final class`, `DomainError` | A PIN refused: switch off, wrong PIN, too many attempts (`retryAfter`) |
| `Domain\Repository\RegisterRepository`, `CashSessionRepository`, `RegisterSaleRepository` | interfaces | The storage |
| `Application\Registers` | `final readonly class` | Creating and finding registers and their terminals, one main register per location |
| `Application\CashSessions` | `final readonly class` | Opening, cash movements, closing, reports |
| `Application\SessionReport` | `final readonly class` | The end-of-day report |
| `Application\RegisterCheckout` | `final readonly class` | Holding and picking up, tipping, ringing up under a preset |
| `Application\RegisterPayments` | `final readonly class` | Cash, recorded methods, counting, refunds |
| `Application\CashTender` | `final readonly class` | Cash taken: due, tendered, change |
| `Application\RegisterGateway` | `final readonly class`, the core's `PaymentGateway` | `cash` and the recorded methods |
| `Application\Layaways` | `final readonly class` | Deposits, opening, settling, cancelling |
| `Application\OfflineSale` | `final readonly class` | An offline sale, read |
| `Application\OfflineSales` | `final readonly class` | Importing offline sales |
| `Application\Contract\OfflineSaleGate` | interface | Whether whoever sends an offline sale may import it |
| `Application\Contract\PaymentTerminal` | interface | An adapter of a processor's connected terminals |
| `Application\Contract\TillDrawForm` | interface | The form that enters a drawer's draws at the counter |
| `Application\TerminalPayments` | `final readonly class` | Charging, cancelling, and the processors' notifications |
| `Application\TerminalGateway` | `final readonly class`, the core's `PaymentGateway` | The connected terminals' method |
| `Application\TerminalAdapters` | `final readonly class` | The adapters, by name or by notification |
| `Application\TerminalCharge` | `final readonly class` | What a terminal is asked to charge |
| `Application\SalePresets` | `final readonly class` | Creating, listing and applying presets |
| `Application\CustomerDisplay`, `DisplayedCart` | `final readonly class` | What the customer display shows |
| `Application\TillDraws`, `PendingDraw` | `final readonly class` | Draws entered at the counter |
| `Application\CashDifferences`, `CashDifferenceReport` | `final readonly class` | The cash-difference report |
| `Application\CashierSwitch` | `final readonly class` | The cashier switch by PIN |
| `Infrastructure\Doctrine\DoctrinePosRepository` | `final readonly class` | Every repository on the entity manager |
| `Infrastructure\Http\OfflineSaleController` | `final readonly class` | Where registers send offline sales |
| `Infrastructure\Http\TerminalNotificationController` | `final readonly class` | Where processors send their notifications |
| `Infrastructure\Terminal\FakePaymentTerminal` | `final class`, `PaymentTerminal` | The fake adapter, `fake` |
| `assets/src/offline_queue.js` | JavaScript module | `OfflineQueue`, `IndexedDbStore`, `newSaleId()` |
| `assets/src/offline_queue_controller.js` | Stimulus controller | `offline-queue` |

See [scope.md](scope.md) for what is not started.
