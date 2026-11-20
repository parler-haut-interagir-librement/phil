# Reference

Every public type of the module.

| Type | Kind | Role |
|---|---|---|
| `PhilCommerceWholesaleBundle` | `final class`, `AbstractBundle` | Alias `phil_commerce_wholesale`; refuses to load without `PhilCommerceBundle` |
| `Domain\Model\CustomerGroup` | `final class`, Doctrine entity | A group: its share off, its minimum order |
| `Domain\Model\Membership` | `final class`, Doctrine entity | A buyer's place in a group |
| `Domain\Model\QuantityRule` | `final class`, Doctrine entity | A SKU's minimum, maximum and increment, for every group or one |
| `Domain\Quantity\QuantityBreach` | string-backed enum | `BelowMinimum`, `AboveMaximum`, `OffIncrement` |
| `Domain\Model\CreditAccount` | `final class`, Doctrine entity | A buyer's credit: limit, terms, what is owed |
| `Domain\Model\Receivable` | `final class`, Doctrine entity | A purchase on account: due date, paid, credited, outstanding, refund owed back |
| `Domain\Exception\WholesaleRefused` | `final class`, `DomainError` | An unknown group, account or receivable; a purchase beyond the limit |
| `Domain\Exception\QuantityRefused` | `final class`, `DomainError` | A quantity of a SKU its rule refuses in the cart, with the validator's messages |
| `Domain\Repository\CustomerGroupRepository` | interface | Groups and memberships |
| `Domain\Repository\CreditRepository` | interface | Accounts and receivables |
| `Domain\Repository\QuantityRuleRepository` | interface | Quantity rules |
| `Application\CreditSales` | `final readonly class` | Accounts, purchases on account, the buyer's payments, overdue receivables |
| `Application\AccountGateway` | `final readonly class`, the core's `PaymentGateway` | The method `on_account`; refunds credit the receivable |
| `Application\CustomerGroups` | `final readonly class` | Joining, leaving, a buyer's group, an order's shortfall |
| `Application\GroupPrices` | `final readonly class`, the core's `UnitPriceResolver` | The group's price, asked after the catalogue's price books |
| `Application\QuantityRules` | `final readonly class` | Setting and removing rules; the rule a line of a SKU follows for a group |
| `Infrastructure\Doctrine\DoctrineWholesaleRepository` | `final readonly class` | The three repositories on the entity manager |
| `Infrastructure\Validator\AllowedQuantity` | `final class`, Validator `Constraint` | A quantity its SKU's rule allows |
| `Infrastructure\Validator\AllowedQuantityValidator` | `final class`, `ConstraintValidator` | Checks it against `QuantityRules` |
| `Infrastructure\Validator\RequestedQuantity` | `final readonly class` | The SKU, the quantity and the customer group validated |
| `Infrastructure\Validator\QuantityGuard` | `final readonly class`, the core's `UnitPriceResolver` | The rules checked in the cart, asked before every price |

See [scope.md](scope.md) for what is not started.
