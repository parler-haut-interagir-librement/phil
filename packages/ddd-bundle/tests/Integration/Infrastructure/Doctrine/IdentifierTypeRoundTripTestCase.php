<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Integration\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Override;
use Phil\DddBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ExampleId;
use Phil\DddBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ExampleIdType;
use PHPUnit\Framework\TestCase;

/**
 * The round trip of an identifier through a real column, on one database server.
 *
 * Each subclass names the environment variable that holds the URL of its server (a DBAL URL such as
 * `mysql://user:password@127.0.0.1:3306/probe`). **Without it, the test is skipped**, so the bundle's test
 * suite still passes on a machine with no database server at all. The test creates its own table, and drops
 * it whether it passes or fails.
 */
abstract class IdentifierTypeRoundTripTestCase extends TestCase
{
    private const string TABLE = 'phil_ddd_identifier_probe';

    protected Connection $connection;

    protected AbstractPlatform $platform;

    private ExampleIdType $type;

    /**
     * @return non-empty-string
     */
    abstract protected static function urlVariable(): string;

    /**
     * The identifier as the column itself spells it, read by the server rather than through the type.
     */
    abstract protected function storedForm(): string;

    /**
     * A UUIDv4 in the column's storage form: a value that no code of the application writes.
     */
    abstract protected function foreignValue(): string;

    #[Override]
    protected function setUp(): void
    {
        $url = getenv(static::urlVariable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', static::urlVariable()));
        }

        $parser = new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql']);
        $this->connection = DriverManager::getConnection($parser->parse($url));
        $this->platform = $this->connection->getDatabasePlatform();
        $this->type = new ExampleIdType();

        $declaration = $this->type->getSQLDeclaration([], $this->platform);

        $this->connection->executeStatement('DROP TABLE IF EXISTS '.self::TABLE);
        $this->connection->executeStatement(\sprintf(
            'CREATE TABLE %s (id %2$s NOT NULL PRIMARY KEY, parent_id %2$s NULL)',
            self::TABLE,
            $declaration,
        ));
    }

    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->executeStatement('DROP TABLE IF EXISTS '.self::TABLE);
            $this->connection->close();
        }
    }

    /**
     * What a schema comparator does: the column read back by introspection declares the same SQL as the type
     * that created it, so a migration diff finds nothing to change.
     */
    public function testTheIntrospectedColumnsDeclareWhatTheTypeDeclares(): void
    {
        $columns = $this->connection->createSchemaManager()->introspectTableColumnsByUnquotedName(self::TABLE);
        $declaration = $this->type->getSQLDeclaration([], $this->platform);

        self::assertSame(
            [$declaration, $declaration],
            array_map(
                fn (Column $column): string => $column->getType()->getSQLDeclaration($column->toArray(), $this->platform),
                $columns,
            ),
        );
    }

    public function testAnIdentifierIsWrittenFoundAndReadBack(): void
    {
        $id = ExampleId::generate();
        $this->connection->insert(self::TABLE, ['id' => $id, 'parent_id' => null], ['id' => $this->type, 'parent_id' => $this->type]);

        $row = $this->connection->fetchAssociative(
            'SELECT id, parent_id FROM '.self::TABLE.' WHERE id = ?',
            [$id],
            [$this->type],
        );

        self::assertIsArray($row);
        $read = $this->type->convertToPHPValue($row['id'], $this->platform);
        self::assertInstanceOf(ExampleId::class, $read);
        self::assertTrue($id->equals($read));
        self::assertNull($this->type->convertToPHPValue($row['parent_id'], $this->platform));
        self::assertSame($id->toString(), $this->storedForm());
    }

    /**
     * UUIDv7 are time-ordered, and the column keeps that order: an index on it appends rather than splits.
     */
    public function testTheColumnSortsIdentifiersInTheOrderTheyWereGenerated(): void
    {
        $first = ExampleId::generate();
        $second = ExampleId::generate();
        $this->connection->insert(self::TABLE, ['id' => $second], ['id' => $this->type]);
        $this->connection->insert(self::TABLE, ['id' => $first], ['id' => $this->type]);

        $ids = array_map(
            fn (mixed $value): ?ExampleId => $this->type->convertToPHPValue($value, $this->platform),
            $this->connection->fetchFirstColumn('SELECT id FROM '.self::TABLE.' ORDER BY id'),
        );

        self::assertSame([$first->toString(), $second->toString()], array_map(
            static fn (?ExampleId $id): ?string => $id?->toString(),
            $ids,
        ));
    }

    public function testAForeignValueIsRefusedOnTheWayOut(): void
    {
        $this->connection->insert(self::TABLE, ['id' => $this->foreignValue()]);

        $this->expectException(ValueNotConvertible::class);

        $this->type->convertToPHPValue($this->connection->fetchOne('SELECT id FROM '.self::TABLE), $this->platform);
    }
}
