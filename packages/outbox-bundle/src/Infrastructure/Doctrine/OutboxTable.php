<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Types\Types;
use InvalidArgumentException;
use Phil\Outbox\OutboxRecord;

/**
 * The table of the outbox and its definition. `position` gives the order the records were added in; the identifier,
 * the type and the keys compare byte by byte — the `ascii_bin` collation of MySQL and MariaDB, the `C` collation of
 * PostgreSQL, SQLite comparing bytes already —, so that `set:A` is never `set:a`. Moments are kept as seconds since the
 * Unix epoch. The name is checked before it reaches SQL.
 */
final readonly class OutboxTable
{
    public const string DEFAULT_NAME = 'phil_outbox';

    /** The shape of a table name: 1 to 48 lowercase letters, digits or `_`, not starting with a digit. */
    public const string NAME_PATTERN = '/^[a-z_][a-z0-9_]{0,47}$/D';

    /** @var non-empty-string */
    public string $name;

    /**
     * @throws InvalidArgumentException when the name is not lowercase letters, digits and `_`, of 1 to 48 characters,
     *                                  not starting with a digit
     */
    public function __construct(string $name = self::DEFAULT_NAME)
    {
        if ('' === $name || 1 !== preg_match(self::NAME_PATTERN, $name)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a table name: 1 to 48 lowercase letters, digits or "_", not starting with a digit.', mb_strimwidth($name, 0, 60, '…')));
        }

        $this->name = $name;
    }

    /**
     * Adds the table to a schema that lacks it: the one the ORM generates, through `OutboxSchemaListener`, or one a
     * migration or `phil:outbox:schema` builds.
     */
    public function configureSchema(Schema $schema, AbstractPlatform $platform): Schema
    {
        if ($schema->hasTable($this->name)) {
            return $schema;
        }

        return $schema->edit()->addTable($this->definition($platform))->create();
    }

    public function definition(AbstractPlatform $platform): Table
    {
        return Table::editor()
            ->setUnquotedName($this->name)
            ->setColumns(
                Column::editor()->setUnquotedName('position')->setTypeName(Types::BIGINT)->setAutoincrement(true)->create(),
                $this->bytes($platform, 'id', 32, false),
                $this->bytes($platform, 'type', 120, false),
                Column::editor()->setUnquotedName('payload')->setTypeName(Types::TEXT)->create(),
                $this->bytes($platform, 'ordering_key', 190, true),
                $this->bytes($platform, 'idempotency_key', 190, true),
                $this->bytes($platform, 'state', 16, false),
                Column::editor()->setUnquotedName('attempts')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('occurred_at')->setTypeName(Types::BIGINT)->create(),
                Column::editor()->setUnquotedName('available_at')->setTypeName(Types::BIGINT)->create(),
                $this->bytes($platform, 'claimed_by', 32, true),
                Column::editor()->setUnquotedName('lease_until')->setTypeName(Types::BIGINT)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('last_error')->setTypeName(Types::STRING)->setLength(OutboxRecord::MAX_ERROR_BYTES)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('settled_at')->setTypeName(Types::BIGINT)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('position')->create())
            ->setUniqueConstraints(
                UniqueConstraint::editor()->setUnquotedName($this->name.'_id')->setUnquotedColumnNames('id')->create(),
                UniqueConstraint::editor()->setUnquotedName($this->name.'_idempotency')->setUnquotedColumnNames('type', 'idempotency_key')->create(),
            )
            ->setIndexes(
                $this->index('due', 'state', 'available_at', 'position'),
                $this->index('ordering', 'ordering_key', 'position'),
                $this->index('claim', 'claimed_by'),
                $this->index('settled', 'state', 'settled_at'),
            )
            ->create();
    }

    /**
     * @param non-empty-string $name
     * @param positive-int     $length
     */
    private function bytes(AbstractPlatform $platform, string $name, int $length, bool $nullable): Column
    {
        $column = Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->setNotNull(!$nullable);

        if ($platform instanceof AbstractMySQLPlatform) {
            $column->setCharset('ascii')->setCollation('ascii_bin');
        } elseif ($platform instanceof PostgreSQLPlatform) {
            $column->setCollation('C');
        }

        return $column->create();
    }

    /**
     * @param non-empty-string ...$columns
     */
    private function index(string $suffix, string ...$columns): Index
    {
        return Index::editor()->setUnquotedName($this->name.'_'.$suffix)->setUnquotedColumnNames(...$columns)->create();
    }
}
