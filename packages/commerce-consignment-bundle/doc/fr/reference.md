<!-- phil-dev source-sha256=2b4ec9ffc6f61634be0831bd17e9816c8ab78a2430e46fe9f9db5c55c9607adf -->
# Référence

Chaque type public du paquet, avec son rôle.

## Services

| Type | Rôle |
|---|---|
| `Phil\CommerceConsignmentBundle\PhilCommerceConsignmentBundle` | Le bundle : sa configuration `phil_commerce_consignment` et ses services. |
| `Application\ConsignmentStock` | Reçoit, adopte, vend, reprend, transfère et inventorie, au grand livre et dans le stock ; la répartition par propriétaire. |
| `Application\ShippedOrders` | Écrit au grand livre ce qu'une commande a expédié, une seule fois, au prix de ses lignes. |
| `Application\ConfiguredIssueOrder` | L'ordre de sortie de la configuration, pour tous les SKU et par SKU. |
| `Application\ConsignorAgreements` | Les versions de l'entente de chaque consignataire, vérifiées contre le passé ; la version en vigueur à un instant. |
| `Application\ConsignorStatements` | Émet les relevés d'une période, lus dans le grand livre ; la dernière période écoulée selon la fréquence de l'entente. |
| `Application\ConsignorPayouts` | Écrit les versements et leurs contrepassations ; le solde d'un consignataire. |
| `Application\UnsoldStock` | Les unités à l'échéance ; les unités rendues à leur consignataire ; les unités achetées par la boutique. |
| `Application\Export\StatementCsv` | Un relevé en CSV. |
| `Application\Export\StatementView` | Un relevé en données simples pour un gabarit, et le nom du gabarit du bundle. |
| `Domain\Service\Ledger` | Les couches et leurs écritures : réceptions, ventes, retours, transferts, inventaires, la répartition. |
| `Domain\Service\StandardMargins` | Les marges et les commissions par défaut. |

## Valeurs

| Type | Rôle |
|---|---|
| `Domain\ValueObject\Owner` | La boutique ou un consignataire. |
| `Domain\ValueObject\ConsignmentTerms` | Une part par unité ou une commission en parties par million. |
| `Domain\ValueObject\Receipt`, `ReceiptLine` | Marchandises reçues, ligne par ligne achetées ou consignées. |
| `Domain\ValueObject\Sale`, `SaleLine` | Unités vendues ou retournées, par couche, avec leur marge et ce que chaque consignataire est en droit de recevoir. |
| `Domain\ValueObject\Margin` | Revenu, coût ou part du consignataire, marge ou commission. |
| `Domain\ValueObject\Movement` | Unités qu'un transfert a mises dans une couche. |
| `Domain\ValueObject\OwnerQuantity` | Les unités d'un SKU que détient un propriétaire. |
| `Domain\ValueObject\StatementPeriod` | Deux instants en UTC, le début inclus, la fin exclue. |
| `Domain\ValueObject\StatementLine` | Une écriture telle qu'un relevé la montre. |
| `Domain\ValueObject\HeldUnits` | Les unités d'une couche encore en boutique à un instant, avec leur âge. |
| `Domain\ValueObject\ConsignorBalance` | Ce que la boutique doit à un consignataire, ce qu'elle a versé, le solde et son état. |
| `Domain\ValueObject\DueUnits` | Les unités d'une couche dont la consignation est terminée, et ce qu'il faut en faire. |
| `Domain\ValueObject\ReturnSlip`, `SlipLine` | Les unités rendues ou rachetées, par couche, avec ce qu'un rachat doit. |
| `Domain\Consignor\UnsoldPolicy` | `ReturnToConsignor`, `Extend`, `BuyOut`. |
| `Domain\Consignor\StatementFrequency` | `Weekly`, `Monthly`, `Quarterly` ; la dernière période écoulée à un instant. |
| `Domain\Consignor\PayoutState` | `Due`, `PartlyPaid`, `Paid`. |
| `Domain\Stock\IssueOrder` | `OwnedFirst`, `ConsignedFirst`, `OldestFirst`. |
| `Domain\Stock\EntryKind` | Pourquoi une couche a bougé. |
| `Application\ShippedQuantity` | Unités d'un SKU qu'une commande a expédiées d'un emplacement. |
| `Application\Message\RecordShipment` | Le numéro d'une commande, pour un worker. |

## Entités et ports

| Type | Rôle |
|---|---|
| `Domain\Model\StockLayer` | Des unités d'un SKU, à un emplacement, d'un propriétaire, aux mêmes modalités ; versionnée. |
| `Domain\Model\LedgerEntry` | Un mouvement d'une couche, jamais modifié. |
| `Domain\Model\ConsignmentAgreement` | Une version de l'entente d'un consignataire, jamais modifiée. |
| `Domain\Model\ConsignorStatement` | Un relevé numéroté, figé à son émission. |
| `Domain\Model\ConsignorPayout` | Un versement ou sa contrepassation, jamais modifié. |
| `Domain\Contract\LedgerRepository` | Trouve et enregistre les couches et les écritures ; les écritures d'un consignataire, ses unités en main et ce qui lui est dû. |
| `Domain\Contract\ConsignorRepository` | Trouve et enregistre les ententes, les relevés et les versements. |
| `Application\Contract\StatementNumbering` | Donne le numéro de relevé suivant. |
| `Domain\Contract\MarginCalculator` | Calcule une marge ou une commission. |
| `Application\Contract\IssueOrderPolicy` | L'ordre de sortie d'un SKU. |
| `Application\Contract\StockMovements` | Déplace le stock que tient un autre module. |
| `Application\Contract\ShippedUnits` | Ce qu'une commande a expédié, par SKU et par emplacement. |
| `Domain\Exception\ConsignmentRefused` | Une opération refusée avant que rien ne bouge. |
| `Domain\Exception\InvalidValue` | Une valeur que le grand livre ne peut pas contenir. |

## Adaptateurs

| Type | Rôle |
|---|---|
| `Infrastructure\Doctrine\DoctrineLedgerRepository` | Le grand livre en deux tables ; ce qui n'est pas encore écrit par `flush` compte. |
| `Infrastructure\Doctrine\DoctrineConsignorRepository` | Les ententes, les relevés et les versements en trois tables. |
| `Infrastructure\Doctrine\DoctrineStatementNumbering` | La série des relevés, comptée dans leur table. |
| `Infrastructure\Doctrine\BinaryKeysListener` | La collation binaire des clés sur MySQL et MariaDB. |
| `Infrastructure\Memory\InMemoryLedgerRepository` | Le grand livre en mémoire, pour les tests. |
| `Infrastructure\Memory\InMemoryConsignorRepository` | Les ententes, les relevés et les versements en mémoire, pour les tests. |
| `Infrastructure\Memory\InMemoryStatementNumbering` | Une série comptée en mémoire, pour les tests. |
| `Infrastructure\Logistics\LogisticsStockMovements` | Le stock de `phil/commerce-logistics-bundle`. |
| `Infrastructure\Logistics\LogisticsShippedUnits` | Les unités expédiées, lues dans ses réservations. |
| `Infrastructure\Logistics\UnitsShippedListener` | Écrit les ventes d'une commande quand le module de logistique annonce `UnitsShipped`. |
| `Infrastructure\Messenger\RecordShipmentHandler` | Traite `RecordShipment`. |

## Gabarit

| Nom | Rôle |
|---|---|
| `@PhilCommerceConsignment/statement.html.twig` | Un relevé, à partir de `StatementView::data()` ; remplacé dans `templates/bundles/PhilCommerceConsignmentBundle/`. |
