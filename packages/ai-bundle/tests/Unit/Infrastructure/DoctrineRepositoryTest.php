<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\AiBundle\Domain\Model\JournalEntry;
use Phil\AiBundle\Domain\Repository\JournalRepository;
use Phil\AiBundle\Infrastructure\Doctrine\DoctrineJournalRepository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The journal on SQLite in memory, its table created from the mapping, each row read back with the entity manager
 * cleared.
 */
#[CoversClass(DoctrineJournalRepository::class)]
#[CoversClass(JournalEntry::class)]
final class DoctrineRepositoryTest extends RepositoryTestCase
{
    private ?EntityManager $entityManager = null;

    public function testTheTableHoldsNoContent(): void
    {
        $this->repository();
        $schema = $this->entityManager?->getConnection()->createSchemaManager()->introspectSchema();

        self::assertNotNull($schema);
        $table = $schema->getTable(JournalEntry::TABLE);
        self::assertSame(['id'], $table->getPrimaryKey()?->getColumns());
        self::assertSame([
            'id' => [36, true], 'kind' => [64, true], 'subject' => [255, true], 'platform' => [64, true], 'model' => [128, true],
            'key_source' => [16, true], 'outcome' => [16, true], 'input_tokens' => [null, true], 'output_tokens' => [null, true],
            'cost_micros' => [null, true], 'currency' => [3, true], 'started_at' => [null, true], 'duration_ms' => [null, true],
            'decision' => [16, true], 'output' => [16, true], 'decided_at' => [null, false],
        ], array_merge(...array_map(static fn (Column $column): array => [$column->getName() => [$column->getLength(), $column->getNotnull()]], $table->getColumns())));
        self::assertSame(['subject', 'started_at'], $table->getIndex('phil_ai_journal_subject_started')->getColumns());
    }

    #[Override]
    protected function repository(): JournalRepository
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $configuration);
        new SchemaTool($this->entityManager)->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

        return new DoctrineJournalRepository($this->entityManager);
    }

    #[Override]
    protected function clear(): void
    {
        $this->entityManager?->clear();
    }
}
