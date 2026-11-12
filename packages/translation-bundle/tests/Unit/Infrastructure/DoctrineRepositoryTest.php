<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\TranslationBundle\Domain\Model\CommentRecord;
use Phil\TranslationBundle\Domain\Model\MemoryRecord;
use Phil\TranslationBundle\Domain\Model\SegmentRecord;
use Phil\TranslationBundle\Domain\Model\TermRecord;
use Phil\TranslationBundle\Infrastructure\Doctrine\DoctrineCommentRepository;
use Phil\TranslationBundle\Infrastructure\Doctrine\DoctrineMemoryStore;
use Phil\TranslationBundle\Infrastructure\Doctrine\DoctrineSegmentRepository;
use Phil\TranslationBundle\Infrastructure\Doctrine\DoctrineTermRepository;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Memory\MemoryUnit;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\MockClock;

/**
 * The stores on SQLite in memory, their tables created from the mapping as an application's schema tools create them,
 * and each row read back from the database, the entity manager cleared.
 */
#[CoversClass(DoctrineSegmentRepository::class)]
#[CoversClass(DoctrineTermRepository::class)]
#[CoversClass(DoctrineMemoryStore::class)]
#[CoversClass(SegmentRecord::class)]
#[CoversClass(CommentRecord::class)]
#[CoversClass(DoctrineCommentRepository::class)]
#[CoversClass(MemoryRecord::class)]
#[CoversClass(TermRecord::class)]
final class DoctrineRepositoryTest extends RepositoryTestCase
{
    private ?EntityManager $entityManager = null;

    public function testTheTablesAreNamedAndKeyedForTheirLookups(): void
    {
        $this->stores();
        $schema = $this->entityManager?->getConnection()->createSchemaManager()->introspectSchema();

        self::assertNotNull($schema);
        self::assertSame(['document', 'target_language', 'segment_key'], $schema->getTable(SegmentRecord::TABLE)->getPrimaryKey()?->getColumns());
        self::assertSame(['source_language', 'target_language', 'fingerprint'], $schema->getTable(MemoryRecord::TABLE)->getPrimaryKey()?->getColumns());
        self::assertSame(['source_language', 'target_language', 'term'], $schema->getTable(TermRecord::TABLE)->getPrimaryKey()?->getColumns());
        self::assertSame([
            'document' => [255, true], 'source_language' => [35, true], 'target_language' => [35, true], 'segment_key' => [255, true],
            'position' => [null, true], 'source' => [null, true], 'fingerprint' => [64, true], 'state' => [16, true],
            'target' => [null, false], 'translated_source' => [null, false], 'updated_at' => [null, true],
            'translated_by' => [255, false], 'reviewed_by' => [255, false], 'machine' => [null, true], 'flags' => [1024, true],
        ], $this->columns($schema->getTable(SegmentRecord::TABLE)->getColumns()));
        self::assertSame([
            'source_language' => [35, true], 'target_language' => [35, true], 'fingerprint' => [64, true], 'length' => [null, true],
            'source' => [null, true], 'target' => [null, true], 'origin' => [255, false], 'updated_at' => [null, true],
        ], $this->columns($schema->getTable(MemoryRecord::TABLE)->getColumns()));
        self::assertSame([
            'source_language' => [35, true], 'target_language' => [35, true], 'term' => [255, true], 'translations' => [null, true],
            'forbidden' => [null, true], 'case_sensitive' => [null, true], 'note' => [null, false], 'forms' => [null, true],
            'translation_forms' => [null, true],
        ], $this->columns($schema->getTable(TermRecord::TABLE)->getColumns()));
        self::assertSame(['id'], array_map(static fn (UnqualifiedName $name): string => $name->getIdentifier()->getValue(), $schema->getTable(CommentRecord::TABLE)->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame(['document', 'target_language', 'segment_key'], array_map(static fn (IndexedColumn $column): string => $column->getColumnName()->getIdentifier()->getValue(), $schema->getTable(CommentRecord::TABLE)->getIndex('phil_translation_comment_segment')->getIndexedColumns()));
        self::assertSame([
            'id' => [null, true], 'document' => [255, true], 'target_language' => [35, true], 'segment_key' => [255, true],
            'author' => [255, false], 'body' => [null, true], 'created_at' => [null, true],
        ], $this->columns($schema->getTable(CommentRecord::TABLE)->getColumns()));
    }

    public function testAMemoryUnitIsDatedByTheClock(): void
    {
        [, , $memory] = $this->stores();
        $memory->add(new MemoryUnit(LanguageTag::of('en'), LanguageTag::of('fr'), 'Hello', 'Bonjour'));
        $this->clear();

        $entityManager = $this->entityManager;
        self::assertInstanceOf(EntityManager::class, $entityManager);
        self::assertSame('1793959200.000000', $entityManager->getRepository(MemoryRecord::class)->findOneBy([])?->updatedAt()->format('U.u'));

        $memory->add(new MemoryUnit(LanguageTag::of('en'), LanguageTag::of('fr'), 'Hello', 'Allô'));
        self::assertSame('1793959200.000000', $entityManager->getRepository(MemoryRecord::class)->findOneBy([])?->updatedAt()->format('U.u'));
    }

    /**
     * @param list<Column> $columns
     *
     * @return array<string, array{?int, bool}>
     */
    private function columns(array $columns): array
    {
        $read = [];

        foreach ($columns as $column) {
            $read[$column->getName()] = [$column->getLength(), $column->getNotnull()];
        }

        return $read;
    }

    #[Override]
    protected function stores(): array
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        new SchemaTool($this->entityManager)->createSchema([
            $this->entityManager->getClassMetadata(SegmentRecord::class),
            $this->entityManager->getClassMetadata(TermRecord::class),
            $this->entityManager->getClassMetadata(MemoryRecord::class),
            $this->entityManager->getClassMetadata(CommentRecord::class),
        ]);

        return [
            new DoctrineSegmentRepository($this->entityManager),
            new DoctrineTermRepository($this->entityManager),
            new DoctrineMemoryStore($this->entityManager, new MockClock('2026-11-06 10:00:00.5 UTC')),
            new DoctrineCommentRepository($this->entityManager),
        ];
    }

    #[Override]
    protected function clear(): void
    {
        $this->entityManager?->clear();
    }
}
