<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Phil\NewsletterBundle\Infrastructure\Dbal\NewsletterSchema;
use Phil\NewsletterBundle\Tests\Unit\Fixture\Snapshot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NewsletterSchema::class)]
final class NewsletterSchemaTest extends TestCase
{
    public function testTheTablesOfTheNewsletter(): void
    {
        $schema = NewsletterSchema::configure($this->empty());
        $text = '';
        $tables = $schema->getTables();
        usort($tables, static fn (Table $a, Table $b): int => $a->getObjectName()->toString() <=> $b->getObjectName()->toString());

        foreach ($tables as $table) {
            $text .= $this->describe($table);
        }

        Snapshot::assertSame('schema', $text);
        self::assertEqualsCanonicalizing(NewsletterSchema::TABLES, array_map(static fn (Table $table): string => $table->getObjectName()->toString(), $tables));
    }

    public function testATableThatIsThereGainsTheColumnsAndIndexesItLacks(): void
    {
        $id = Column::editor()->setUnquotedName('id')->setTypeName(Types::STRING)->setLength(32)->create();
        $only = Table::editor()->setUnquotedName(NewsletterSchema::DELIVERIES)->setColumns($id, Column::editor()->setUnquotedName('only')->setTypeName(Types::STRING)->setLength(10)->create())
            ->addIndex(Index::editor()->setUnquotedName(NewsletterSchema::DELIVERIES.'_i1')->setUnquotedColumnNames('only')->create())
            ->create();
        $schema = $this->empty()->edit()->addTable($only)->create();

        $configured = NewsletterSchema::configure($schema);
        $deliveries = $configured->getTable(NewsletterSchema::DELIVERIES);

        self::assertCount(\count(NewsletterSchema::TABLES), $configured->getTables());
        self::assertSame(
            ['id', 'only', 'subscriber_id', 'campaign_id', 'template', 'status', 'attempts', 'created_at', 'sent_at', 'run_id'],
            array_map(static fn (Column $column): string => $column->getObjectName()->toString(), $deliveries->getColumns()),
        );
        self::assertSame(['only'], array_map(static fn (IndexedColumn $column): string => $column->getColumnName()->toString(), $deliveries->getIndex(NewsletterSchema::DELIVERIES.'_i1')->getIndexedColumns()));
        self::assertSame(['run_id', 'status', 'id'], array_map(static fn (IndexedColumn $column): string => $column->getColumnName()->toString(), $deliveries->getIndex(NewsletterSchema::DELIVERIES.'_i2')->getIndexedColumns()));
        self::assertNull($deliveries->getPrimaryKeyConstraint(), 'A key is not added to a table that is there.');
        $names = array_map(static fn (Index $index): string => $index->getObjectName()->toString(), $deliveries->getIndexes());
        sort($names);
        self::assertSame([NewsletterSchema::DELIVERIES.'_i0', NewsletterSchema::DELIVERIES.'_i1', NewsletterSchema::DELIVERIES.'_i2'], $names);
        self::assertTrue($configured->hasTable(NewsletterSchema::SUBSCRIBERS));
    }

    public function testATableOfTheLastVersionGainsNothing(): void
    {
        $configured = NewsletterSchema::configure($this->empty());

        self::assertEquals($configured, NewsletterSchema::configure($configured));
    }

    public function testAnInstallOfAnEarlierVersionIsMigratedWithItsRowsKept(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE phil_newsletter_delivery (id VARCHAR(32) NOT NULL, subscriber_id VARCHAR(36) NOT NULL, campaign_id VARCHAR(36) DEFAULT NULL, template VARCHAR(64) DEFAULT NULL, status VARCHAR(16) NOT NULL, attempts INTEGER NOT NULL, created_at BIGINT NOT NULL, sent_at BIGINT DEFAULT NULL, PRIMARY KEY (id))');
        $connection->insert(NewsletterSchema::DELIVERIES, ['id' => 'd1', 'subscriber_id' => 's1', 'campaign_id' => 'c1', 'template' => null, 'status' => 'sent', 'attempts' => 1, 'created_at' => 1, 'sent_at' => 2]);

        $manager = $connection->createSchemaManager();

        $manager->migrateSchema(NewsletterSchema::configure($manager->introspectSchema()));

        self::assertSame(['id' => 'd1', 'run_id' => null], $connection->fetchAssociative('SELECT id, run_id FROM phil_newsletter_delivery'));
        self::assertTrue($connection->createSchemaManager()->introspectSchema()->getTable(NewsletterSchema::DELIVERIES)->hasIndex(NewsletterSchema::DELIVERIES.'_i2'));
    }

    public function testTheTablesAreCreatedOnARealConnection(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $manager = $connection->createSchemaManager();
        $manager->migrateSchema(NewsletterSchema::configure($manager->introspectSchema()));

        $names = array_map(static fn (Table $table): string => $table->getObjectName()->toString(), $connection->createSchemaManager()->introspectSchema()->getTables());
        sort($names);
        $expected = NewsletterSchema::TABLES;
        sort($expected);

        self::assertSame($expected, $names);
    }

    private function empty(): Schema
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])->createSchemaManager()->introspectSchema();
    }

    private function describe(Table $table): string
    {
        $text = $table->getObjectName()->toString()."\n";

        foreach ($table->getColumns() as $column) {
            $text .= \sprintf("  %s %s(%s) %s\n", $column->getObjectName()->toString(), $column->getTypeName(), $column->getLength() ?? '-', $column->getNotnull() ? 'not null' : 'null');
        }

        $text .= '  primary '.implode(',', array_map(static fn (UnqualifiedName $name): string => $name->toString(), $table->getPrimaryKeyConstraint()?->getColumnNames() ?? []))."\n";
        $indexes = [];

        foreach ($table->getIndexes() as $index) {
            if ('primary' === strtolower($index->getObjectName()->toString())) {
                continue;
            }

            $indexes[] = \sprintf("  index %s (%s)%s\n", $index->getObjectName()->toString(), implode(',', array_map(static fn (IndexedColumn $column): string => $column->getColumnName()->toString(), $index->getIndexedColumns())), IndexType::UNIQUE === $index->getType() ? ' unique' : '');
        }

        foreach ($table->getUniqueConstraints() as $constraint) {
            $indexes[] = \sprintf("  unique %s (%s)\n", $constraint->getObjectName()?->toString() ?? '-', implode(',', array_map(static fn (UnqualifiedName $name): string => $name->toString(), $constraint->getColumnNames())));
        }

        sort($indexes);

        return $text.implode('', $indexes);
    }
}
