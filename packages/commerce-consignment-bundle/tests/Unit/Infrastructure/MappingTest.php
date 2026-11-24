<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Infrastructure;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Phil\CommerceConsignmentBundle\Domain\Consignor\StatementFrequency;
use Phil\CommerceConsignmentBundle\Domain\Consignor\UnsoldPolicy;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignmentAgreement;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignorPayout;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignorStatement;
use Phil\CommerceConsignmentBundle\Domain\Model\LedgerEntry;
use Phil\CommerceConsignmentBundle\Domain\Model\StockLayer;
use Phil\CommerceConsignmentBundle\Domain\Stock\EntryKind;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ReceiptLine;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\StatementPeriod;
use Phil\CommerceConsignmentBundle\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\Fixtures;
use Phil\Money\Currency;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns of the tables, as their migrations write them: a change here is a change of the schema, which the
 * CHANGELOG says and a migration carries.
 */
#[CoversClass(StockLayer::class)]
#[CoversClass(LedgerEntry::class)]
#[CoversClass(ConsignmentAgreement::class)]
#[CoversClass(ConsignorStatement::class)]
#[CoversClass(ConsignorPayout::class)]
#[CoversClass(BinaryKeysListener::class)]
final class MappingTest extends TestCase
{
    /**
     * Field → column, type, length, nullable, fixed.
     */
    private const array COLUMNS = [
        StockLayer::class => [
            'remaining' => ['remaining', 'integer', null, false, false],
            'version' => ['version', 'integer', null, false, false],
            'id' => ['id', 'string', 36, false, true],
            'origin' => ['origin', 'string', 36, false, true],
            'sku' => ['sku', 'string', 64, false, false],
            'location' => ['location', 'string', 64, false, false],
            'consignor' => ['consignor', 'string', 64, false, false],
            'currency' => ['currency', 'string', 3, true, true],
            'unitCost' => ['unit_cost', 'bigint', null, true, false],
            'share' => ['share', 'bigint', null, true, false],
            'commissionPpm' => ['commission_ppm', 'integer', null, true, false],
            'receivedAt' => ['received_at', 'datetime_utc_immutable', null, false, false],
            'reference' => ['reference', 'string', 64, true, false],
            'received' => ['received', 'integer', null, false, false],
        ],
        ConsignorPayout::class => [
            'id' => ['id', 'string', 36, false, true],
            'idempotencyKey' => ['idempotency_key', 'string', 64, false, false],
            'consignor' => ['consignor', 'string', 64, false, false],
            'currency' => ['currency', 'string', 3, false, true],
            'amount' => ['amount', 'bigint', null, false, false],
            'paidAt' => ['paid_at', 'datetime_utc_immutable', null, false, false],
            'method' => ['method', 'string', 32, false, false],
            'reference' => ['reference', 'string', 64, true, false],
            'reverses' => ['reverses', 'string', 36, true, true],
            'recordedAt' => ['recorded_at', 'datetime_utc_immutable', null, false, false],
        ],
        LedgerEntry::class => [
            'id' => ['id', 'string', 36, false, true],
            'layer' => ['layer', 'string', 36, false, true],
            'sku' => ['sku', 'string', 64, false, false],
            'location' => ['location', 'string', 64, false, false],
            'consignor' => ['consignor', 'string', 64, false, false],
            'kind' => ['kind', 'string', 16, false, false],
            'quantity' => ['quantity', 'integer', null, false, false],
            'reference' => ['reference', 'string', 64, true, false],
            'movedAt' => ['moved_at', 'datetime_utc_immutable', null, false, false],
            'currency' => ['currency', 'string', 3, true, true],
            'unitPrice' => ['unit_price', 'bigint', null, true, false],
            'revenue' => ['revenue', 'bigint', null, true, false],
            'cost' => ['cost', 'bigint', null, true, false],
        ],
        ConsignorStatement::class => [
            'id' => ['id', 'string', 36, false, true],
            'number' => ['number', 'string', 32, false, false],
            'consignor' => ['consignor', 'string', 64, false, false],
            'currency' => ['currency', 'string', 3, false, true],
            'periodStart' => ['period_start', 'datetime_utc_immutable', null, false, false],
            'periodEnd' => ['period_end', 'datetime_utc_immutable', null, false, false],
            'issuedAt' => ['issued_at', 'datetime_utc_immutable', null, false, false],
            'unitsSold' => ['units_sold', 'integer', null, false, false],
            'unitsReturned' => ['units_returned', 'integer', null, false, false],
            'unitsBoughtOut' => ['units_bought_out', 'integer', null, false, false],
            'unitsGivenBack' => ['units_given_back', 'integer', null, false, false],
            'revenue' => ['revenue', 'bigint', null, false, false],
            'share' => ['share', 'bigint', null, false, false],
            'commission' => ['commission', 'bigint', null, false, false],
            'lines' => ['entry_lines', 'json', null, false, false],
            'held' => ['held_units', 'json', null, false, false],
        ],
        ConsignmentAgreement::class => [
            'id' => ['id', 'string', 36, false, true],
            'consignor' => ['consignor', 'string', 64, false, false],
            'effectiveFrom' => ['effective_from', 'datetime_utc_immutable', null, false, false],
            'termDays' => ['term_days', 'integer', null, true, false],
            'unsoldPolicy' => ['unsold_policy', 'string', 16, false, false],
            'extensionDays' => ['extension_days', 'integer', null, true, false],
            'extensions' => ['extensions', 'integer', null, false, false],
            'frequency' => ['frequency', 'string', 16, false, false],
            'recordedAt' => ['recorded_at', 'datetime_utc_immutable', null, false, false],
        ],
    ];

    public function testTheKeysCompareByteByByteOnMysql(): void
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $events = new EventManager();
        $events->addEventListener(Events::loadClassMetadata, new BinaryKeysListener());
        // The server's version is given: the platform is known without connecting.
        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']), $configuration, $events);

        foreach (BinaryKeysListener::FIELDS as $class => $fields) {
            $metadata = $entityManager->getClassMetadata($class);

            foreach ($metadata->getFieldNames() as $field) {
                $options = $metadata->getFieldMapping($field)->options ?? [];
                $binary = \in_array($field, $fields, true);
                self::assertSame($binary ? 'utf8mb4_bin' : null, $options['collation'] ?? null, $class.'::'.$field);
                self::assertSame($binary ? 'utf8mb4' : null, $options['charset'] ?? null, $class.'::'.$field);
            }

            // The other options of a key stay as the mapping writes them.
            self::assertTrue($metadata->getFieldMapping('id')->options['fixed'] ?? false, $class);
        }
    }

    public function testEveryColumnIsMappedAsTheSchemaSays(): void
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $configuration);

        // One entity of each class, so that this test covers their constructors.
        $layer = StockLayer::receive(ReceiptLine::purchased(Fixtures::BOOK, 1, Fixtures::cad(1)), Fixtures::SHELF, 'DN-1', Fixtures::at(9));
        $entities = [
            $layer,
            LedgerEntry::movement($layer, EntryKind::Received, 1, 'DN-1', Fixtures::at(9)),
            ConsignmentAgreement::version(Owner::consignor('c'), Fixtures::at(9), 1, UnsoldPolicy::ReturnToConsignor, StatementFrequency::Monthly, Fixtures::at(9)),
            ConsignorStatement::issue('ST-1', Owner::consignor('c'), Currency::of('CAD'), StatementPeriod::of(Fixtures::at(9), Fixtures::at(10)), [], [], Fixtures::at(10)),
            ConsignorPayout::record('k', Owner::consignor('c'), Fixtures::cad(1), Fixtures::at(9), 'cash', null, Fixtures::at(9)),
        ];

        foreach ($entities as $entity) {
            $metadata = $entityManager->getClassMetadata($entity::class);
            $columns = [];

            foreach ($metadata->getFieldNames() as $field) {
                $mapping = $metadata->getFieldMapping($field);
                $columns[$field] = [$mapping->columnName, $mapping->type, $mapping->length, (bool) $mapping->nullable, (bool) ($mapping->options['fixed'] ?? false)];
            }

            self::assertSame(self::COLUMNS[$entity::class], $columns, $entity::class);
        }
    }
}
