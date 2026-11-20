<!-- phil-dev source-sha256=194f2c4fb2443f43308b4cfc90ae3ec3fe831fd9180a2df9e7c4a71e177bbad1 -->
# Référence

Chaque type public du module.

| Type | Genre | Rôle |
|---|---|---|
| `PhilCommerceWholesaleBundle` | `final class`, `AbstractBundle` | Alias `phil_commerce_wholesale` ; refuse de se charger sans `PhilCommerceBundle` |
| `Domain\Model\CustomerGroup` | `final class`, entité Doctrine | Un groupe : sa remise, sa commande minimale |
| `Domain\Model\Membership` | `final class`, entité Doctrine | La place d'un acheteur dans un groupe |
| `Domain\Model\QuantityRule` | `final class`, entité Doctrine | Le minimum, le maximum et l'incrément d'un *SKU*, pour tous les groupes ou pour un seul |
| `Domain\Quantity\QuantityBreach` | énumération adossée à une chaîne | `BelowMinimum`, `AboveMaximum`, `OffIncrement` |
| `Domain\Model\CreditAccount` | `final class`, entité Doctrine | Le crédit d'un acheteur : limite, modalités, montant dû |
| `Domain\Model\Receivable` | `final class`, entité Doctrine | Un achat à crédit : échéance, payé, crédité, solde, remboursement dû en retour |
| `Domain\Exception\WholesaleRefused` | `final class`, `DomainError` | Un groupe, un compte ou une créance inconnus ; un achat au-delà de la limite |
| `Domain\Exception\QuantityRefused` | `final class`, `DomainError` | Une quantité d'un *SKU* que sa règle refuse dans le panier, avec les messages du validateur |
| `Domain\Repository\CustomerGroupRepository` | interface | Les groupes et les appartenances |
| `Domain\Repository\CreditRepository` | interface | Les comptes et les créances |
| `Domain\Repository\QuantityRuleRepository` | interface | Les règles de quantité |
| `Application\CreditSales` | `final readonly class` | Les comptes, les achats à crédit, les paiements de l'acheteur, les créances en souffrance |
| `Application\AccountGateway` | `final readonly class`, `PaymentGateway` du noyau | La méthode `on_account` ; les remboursements créditent la créance |
| `Application\CustomerGroups` | `final readonly class` | Adhérer, quitter, le groupe d'un acheteur, le manque d'une commande |
| `Application\GroupPrices` | `final readonly class`, `UnitPriceResolver` du noyau | Le prix du groupe, interrogé après les carnets de prix du catalogue |
| `Application\QuantityRules` | `final readonly class` | Fixer et retirer les règles ; la règle que suit une ligne d'un *SKU* pour un groupe |
| `Infrastructure\Doctrine\DoctrineWholesaleRepository` | `final readonly class` | Les trois dépôts sur le gestionnaire d'entités |
| `Infrastructure\Validator\AllowedQuantity` | `final class`, `Constraint` du Validator | Une quantité que la règle de son *SKU* permet |
| `Infrastructure\Validator\AllowedQuantityValidator` | `final class`, `ConstraintValidator` | La vérifie contre `QuantityRules` |
| `Infrastructure\Validator\RequestedQuantity` | `final readonly class` | Le *SKU*, la quantité et le groupe de clients validés |
| `Infrastructure\Validator\QuantityGuard` | `final readonly class`, `UnitPriceResolver` du noyau | Les règles vérifiées dans le panier, interrogé avant tous les prix |

Voir [scope.md](scope.md) pour ce qui n'est pas commencé.
