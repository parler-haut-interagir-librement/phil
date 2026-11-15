<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Column;
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

    public function testATableThatIsThereIsLeftAlone(): void
    {
        $only = Table::editor()->setUnquotedName(NewsletterSchema::LISTS)->setColumns(Column::editor()->setUnquotedName('only')->setTypeName(Types::STRING)->setLength(10)->create())->create();
        $schema = $this->empty()->edit()->addTable($only)->create();

        $configured = NewsletterSchema::configure($schema);

        self::assertCount(\count(NewsletterSchema::TABLES), $configured->getTables());
        self::assertSame(['only'], array_map(static fn (Column $column): string => $column->getObjectName()->toString(), $configured->getTable(NewsletterSchema::LISTS)->getColumns()));
        self::assertTrue($configured->hasTable(NewsletterSchema::SUBSCRIBERS));
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
