<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Override;
use Phil\SettingsBundle\Domain\Contract\SettingMigrationStore;
use Phil\SettingsBundle\Domain\Contract\SettingRepository;
use Phil\SettingsBundle\Domain\Migration\StoredValue;
use Phil\SettingsBundle\Domain\ValueObject\Subject;
use Phil\SettingsBundle\Infrastructure\Doctrine\DbalSettingRepository;
use Phil\SettingsBundle\Infrastructure\Doctrine\SettingSchemaListener;
use Phil\SettingsBundle\Infrastructure\Doctrine\SettingTables;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DbalSettingRepository::class)]
#[CoversClass(SettingTables::class)]
#[CoversClass(SettingSchemaListener::class)]
final class DbalSettingRepositoryTest extends RepositoryTestCase
{
    private Connection $connection;

    public function testOneRowPerSubjectAndKeyWithItsDate(): void
    {
        $repository = $this->repository();
        $repository->save(Subject::user('ada'), ['a.b' => 'true'], [], new DateTimeImmutable('2026-11-08 12:00:00 UTC'));
        $repository->save(Subject::user('ada'), ['a.b' => 'false'], [], new DateTimeImmutable('2026-11-08 13:00:00 UTC'));

        self::assertSame([['user', 'ada', 'a.b', 'false', '2026-11-08 13:00:00']], $this->connection->fetchAllNumeric('SELECT scope, subject, setting_key, value, updated_at FROM phil_settings'));
    }

    public function testASaveIsOneTransaction(): void
    {
        $repository = $this->repository();
        $repository->save(Subject::user('ada'), ['a.b' => 'true'], [], new DateTimeImmutable());

        $this->connection->executeStatement("CREATE TRIGGER refuse BEFORE INSERT ON phil_settings WHEN NEW.setting_key = 'a.z' BEGIN SELECT RAISE(ABORT, 'refused'); END");

        try {
            $repository->save(Subject::user('ada'), ['a.c' => '1', 'a.z' => '2'], ['a.b'], new DateTimeImmutable());
            self::fail('The save succeeded.');
        } catch (Exception) {
            self::assertSame(['a.b' => 'true'], $repository->values(Subject::user('ada')));
        }
    }

    public function testTheTableJoinsTheSchemaOfItsConnection(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $listener = new SettingSchemaListener($connection);
        $schema = Schema::editor()->create();

        $ignored = new GenerateSchemaEventArgs($this->entityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])), $schema);
        $listener->postGenerateSchema($ignored);
        self::assertSame($schema, $ignored->getSchema());
        self::assertFalse($schema->hasTable(SettingTables::SETTINGS));

        $event = new GenerateSchemaEventArgs($this->entityManager($connection), $schema);
        $listener->postGenerateSchema($event);
        $table = $event->getSchema()->getTable(SettingTables::SETTINGS);

        self::assertSame([8, 190, 128], [$table->getColumn('scope')->getLength(), $table->getColumn('subject')->getLength(), $table->getColumn('setting_key')->getLength()]);
        self::assertSame(['text', 'datetime_immutable'], [$table->getColumn('value')->getTypeName(), $table->getColumn('updated_at')->getTypeName()]);
        foreach (['scope', 'subject', 'setting_key', 'value', 'updated_at'] as $column) {
            self::assertTrue($table->getColumn($column)->getNotnull(), $column);
        }

        self::assertSame(['scope', 'subject', 'setting_key'], array_map(static fn (object $name): string => $name->toString(), $table->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame($event->getSchema(), SettingTables::configure($event->getSchema()), 'a schema that has the table is kept');
    }

    public function testTheVersionsOfTheMigrationsHaveTheirTable(): void
    {
        $table = SettingTables::configure(Schema::editor()->create())->getTable(SettingTables::MIGRATIONS);

        self::assertSame(190, $table->getColumn('version')->getLength());
        self::assertSame('datetime_immutable', $table->getColumn('applied_at')->getTypeName());
        self::assertSame(['version'], array_map(static fn (object $name): string => $name->toString(), $table->getPrimaryKeyConstraint()?->getColumnNames() ?? []));

        $repository = $this->repository();
        $repository->applyMigration('v1', [], new DateTimeImmutable('2026-11-12 08:00:00 UTC'));
        self::assertSame([['v1', '2026-11-12 08:00:00']], $this->connection->fetchAllNumeric('SELECT version, applied_at FROM phil_settings_migrations'));
    }

    public function testAMigrationIsOneTransactionAndRunsOnce(): void
    {
        $repository = $this->repository();
        $at = new DateTimeImmutable('2026-11-08 12:00:00 UTC');
        $repository->save(Subject::user('ada'), ['a.b' => '1'], [], $at);
        $repository->applyMigration('v1', [], $at);

        try {
            $repository->applyMigration('v1', [new StoredValue('user', 'ada', 'a.b', null)], $at);
            self::fail('The same version was recorded twice.');
        } catch (UniqueConstraintViolationException) {
            self::assertSame(['a.b' => '1'], $repository->values(Subject::user('ada')));
        }
    }

    public function testASchemaMissingOneTableGetsIt(): void
    {
        $schema = Schema::editor()->create();
        $withSettings = $schema->edit()->addTable(SettingTables::configure($schema)->getTable(SettingTables::SETTINGS))->create();

        self::assertTrue(SettingTables::configure($withSettings)->hasTable(SettingTables::MIGRATIONS));
    }

    public function testTheSameKeyOfTheSameSubjectIsOneRow(): void
    {
        $row = ['scope' => 'user', 'subject' => 'ada', 'setting_key' => 'a.b', 'value' => '1', 'updated_at' => '2026-11-08 12:00:00'];
        $this->connection->insert(SettingTables::SETTINGS, $row);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->connection->insert(SettingTables::SETTINGS, $row);
    }

    #[Override]
    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach ($this->connection->getDatabasePlatform()->getCreateTablesSQL(SettingTables::configure(Schema::editor()->create())->getTables()) as $sql) {
            $this->connection->executeStatement($sql);
        }
    }

    #[Override]
    protected function repository(): SettingRepository&SettingMigrationStore
    {
        return new DbalSettingRepository($this->connection);
    }

    private function entityManager(Connection $connection): EntityManagerInterface
    {
        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        return $entityManager;
    }
}
