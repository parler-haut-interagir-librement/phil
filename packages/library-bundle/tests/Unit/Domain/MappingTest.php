<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Tests\Unit\Domain;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ManyToOneAssociationMapping;
use Doctrine\ORM\ORMSetup;
use Phil\LibraryBundle\Domain\Model\Copy;
use Phil\LibraryBundle\Domain\Model\Loan;
use Phil\LibraryBundle\Domain\Model\Title;
use Phil\LibraryBundle\Domain\Status\CopyCondition;
use Phil\LibraryBundle\Domain\Status\LoanStatus;
use Phil\LibraryBundle\Domain\Status\TitleKind;
use Phil\LibraryBundle\Domain\ValueObject\Record;
use Phil\LibraryBundle\Tests\Unit\Fixture\Library;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The tables and columns the entities map to, as a migration generates them.
 */
#[CoversClass(Title::class)]
#[CoversClass(Copy::class)]
#[CoversClass(Loan::class)]
final class MappingTest extends TestCase
{
    public function testTheTitleTable(): void
    {
        // Built once, so that the attributes of the promoted parameters count as run.
        new Library()->catalogue->add(new Record(TitleKind::Game, 'Azul'));

        self::assertSame([
            'table' => 'phil_library_title',
            'unique' => [],
            'indexes' => [['name'], ['isbn']],
            'columns' => [
                'kind' => ['kind', 'string', 16, false, []],
                'name' => ['name', 'string', 255, false, []],
                'isbn' => ['isbn', 'string', 13, true, ['fixed' => true]],
                'creators' => ['creators', 'string', 255, true, []],
                'publisher' => ['publisher', 'string', 255, true, []],
                'year' => ['year', 'smallint', null, true, []],
                'minPlayers' => ['min_players', 'smallint', null, true, []],
                'maxPlayers' => ['max_players', 'smallint', null, true, []],
                'minMinutes' => ['min_minutes', 'integer', null, true, []],
                'maxMinutes' => ['max_minutes', 'integer', null, true, []],
                'minAge' => ['min_age', 'smallint', null, true, []],
                'language' => ['language', 'string', 35, true, []],
                'summary' => ['summary', 'text', null, true, []],
                'version' => ['version', 'integer', null, false, ['default' => 1]],
                'id' => ['id', 'string', 36, false, ['fixed' => true]],
                'createdAt' => ['created_at', 'datetime_utc_immutable', null, false, []],
            ],
            'associations' => [],
        ], $this->describe(Title::class));
    }

    public function testTheCopyTable(): void
    {
        $library = new Library();
        $library->shelve('Azul', ['G-1']);

        self::assertSame(CopyCondition::Good, $library->catalogue->copyByBarcode('G-1')->condition());
        self::assertSame([
            'table' => 'phil_library_copy',
            'unique' => [['barcode']],
            'indexes' => [['title']],
            'columns' => [
                'note' => ['note', 'string', 1000, true, []],
                'stockroomItem' => ['stockroom_item', 'string', 128, true, []],
                'claims' => ['claims', 'integer', null, false, []],
                'version' => ['version', 'integer', null, false, ['default' => 1]],
                'id' => ['id', 'string', 36, false, ['fixed' => true]],
                'barcode' => ['barcode', 'string', 64, false, []],
                'condition' => ['state', 'string', 16, false, []],
                'createdAt' => ['created_at', 'datetime_utc_immutable', null, false, []],
            ],
            'associations' => ['title' => [Title::class, 'title', false]],
        ], $this->describe(Copy::class));
    }

    public function testTheLoanTable(): void
    {
        $library = new Library();
        $library->shelve('Azul', ['G-1']);

        self::assertSame(LoanStatus::Active, $library->service->lend('G-1', 'card-7')->status());
        self::assertSame([
            'table' => 'phil_library_loan',
            'unique' => [],
            'indexes' => [['copy', 'status', 'due_at'], ['member', 'status'], ['status', 'due_at']],
            'columns' => [
                'checkedOutAt' => ['checked_out_at', 'datetime_utc_immutable', null, true, []],
                'closedAt' => ['closed_at', 'datetime_utc_immutable', null, true, []],
                'renewals' => ['renewals', 'smallint', null, false, []],
                'lastReminder' => ['last_reminder', 'integer', null, true, []],
                'notices' => ['notices', 'smallint', null, false, []],
                'lastNoticeAt' => ['last_notice_at', 'datetime_utc_immutable', null, true, []],
                'version' => ['version', 'integer', null, false, ['default' => 1]],
                'id' => ['id', 'string', 36, false, ['fixed' => true]],
                'member' => ['member', 'string', 128, true, []],
                'status' => ['status', 'string', 16, false, []],
                'startsAt' => ['starts_at', 'datetime_utc_immutable', null, false, []],
                'dueAt' => ['due_at', 'datetime_utc_immutable', null, false, []],
                'createdAt' => ['created_at', 'datetime_utc_immutable', null, false, []],
            ],
            'associations' => ['copy' => [Copy::class, 'copy', false]],
        ], $this->describe(Loan::class));
    }

    /**
     * @param class-string $class
     *
     * @return array<string, mixed>
     */
    private function describe(string $class): array
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $metadata = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $configuration)->getClassMetadata($class);
        $columns = [];
        $associations = [];

        foreach ($metadata->fieldMappings as $name => $mapping) {
            $columns[$name] = [$mapping->columnName, $mapping->type, $mapping->length, true === $mapping->nullable, $mapping->options ?? []];
        }

        foreach ($metadata->associationMappings as $name => $mapping) {
            self::assertInstanceOf(ManyToOneAssociationMapping::class, $mapping);
            $join = $mapping->joinColumns[0];
            $associations[$name] = [$mapping->targetEntity, $join->name, true === $join->nullable];
        }

        return [
            'table' => $metadata->getTableName(),
            'unique' => array_column($metadata->table['uniqueConstraints'] ?? [], 'columns'),
            'indexes' => array_column($metadata->table['indexes'] ?? [], 'columns'),
            'columns' => $columns,
            'associations' => $associations,
        ];
    }
}
