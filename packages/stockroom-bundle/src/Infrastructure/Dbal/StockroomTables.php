<?php

declare(strict_types=1);

namespace Phil\StockroomBundle\Infrastructure\Dbal;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Types\Types;
use InvalidArgumentException;
use Phil\Stockroom\Text;

/**
 * The six tables of the stockroom, their names made of one prefix, and their definitions.
 *
 * Identifiers and place keys compare byte by byte, as the library sorts them: the columns take the `ascii_bin`
 * collation of MySQL and MariaDB and the `C` collation of PostgreSQL, so that `crate-A` is never `crate-a` and a listing
 * comes in the order of the adapters in memory; SQLite compares bytes already. The prefix is checked before it reaches
 * SQL.
 */
final readonly class StockroomTables
{
    public const string DEFAULT_PREFIX = 'phil_stockroom_';

    /** The shape of a prefix: 1 to 40 lowercase letters, digits or `_`, not starting with a digit. */
    public const string PREFIX_PATTERN = '/^[a-z_][a-z0-9_]{0,39}$/D';

    /** The longest identifier column: an identifier, or a place key — its kind, a slash, and an identifier. */
    public const int KEY_LENGTH = 140;

    /** @var non-empty-string */
    public string $containers;

    /** @var non-empty-string */
    public string $items;

    /** @var non-empty-string */
    public string $balances;

    /** @var non-empty-string */
    public string $movements;

    /** @var non-empty-string */
    public string $loadLists;

    /** @var non-empty-string */
    public string $loadLines;

    /**
     * @throws InvalidArgumentException when the prefix is not lowercase letters, digits and `_`, starting with a letter
     *                                  or `_`, of 1 to 40 characters
     */
    public function __construct(
        public string $prefix = self::DEFAULT_PREFIX,
    ) {
        if (1 !== preg_match(self::PREFIX_PATTERN, $prefix)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a prefix of table names: 1 to 40 lowercase letters, digits or "_", not starting with a digit.', mb_strimwidth($prefix, 0, 60, '…')));
        }

        $this->containers = $prefix.'container';
        $this->items = $prefix.'item';
        $this->balances = $prefix.'balance';
        $this->movements = $prefix.'movement';
        $this->loadLists = $prefix.'load_list';
        $this->loadLines = $prefix.'load_line';
    }

    /**
     * The names of the six tables, in the order they are created.
     *
     * @return list<non-empty-string>
     */
    public function names(): array
    {
        return [$this->containers, $this->items, $this->balances, $this->movements, $this->loadLists, $this->loadLines];
    }

    /**
     * Adds the tables a schema lacks: the one the ORM generates, through `StockroomSchemaListener`, or one a migration
     * or `phil:stockroom:schema` builds.
     */
    public function configureSchema(Schema $schema, AbstractPlatform $platform): Schema
    {
        $editor = $schema->edit();

        foreach ($this->definitions($platform) as $table) {
            if (!$schema->hasTable($table->getObjectName()->toString())) {
                $editor->addTable($table);
            }
        }

        return $editor->create();
    }

    /**
     * @return list<Table>
     */
    public function definitions(AbstractPlatform $platform): array
    {
        return [
            Table::editor()
                ->setUnquotedName($this->containers)
                ->setColumns(
                    $this->identifier($platform, 'id'),
                    $this->text('name', Text::NAME),
                    $this->key($platform, 'parent', true),
                    $this->key($platform, 'code', true),
                )
                ->setPrimaryKeyConstraint($this->primaryKey('id'))
                ->setIndexes($this->index($this->containers.'_parent', 'parent', 'id'))
                ->create(),
            Table::editor()
                ->setUnquotedName($this->items)
                ->setColumns(
                    $this->identifier($platform, 'id'),
                    $this->key($platform, 'code', false),
                    $this->text('name', Text::NAME),
                    $this->key($platform, 'tracking', false),
                    $this->key($platform, 'home', true),
                    $this->key($platform, 'item_condition', false),
                )
                ->setPrimaryKeyConstraint($this->primaryKey('id'))
                ->setUniqueConstraints(UniqueConstraint::editor()->setUnquotedName($this->items.'_code')->setUnquotedColumnNames('code')->create())
                ->create(),
            Table::editor()
                ->setUnquotedName($this->balances)
                ->setColumns(
                    $this->identifier($platform, 'item'),
                    $this->identifier($platform, 'place'),
                    Column::editor()->setUnquotedName('quantity')->setTypeName(Types::BIGINT)->create(),
                )
                ->setPrimaryKeyConstraint($this->primaryKey('item', 'place'))
                ->setIndexes($this->index($this->balances.'_place', 'place', 'item'))
                ->create(),
            Table::editor()
                ->setUnquotedName($this->movements)
                ->setColumns(
                    Column::editor()->setUnquotedName('position')->setTypeName(Types::BIGINT)->setAutoincrement(true)->create(),
                    $this->key($platform, 'id', false),
                    $this->key($platform, 'kind', false),
                    $this->key($platform, 'item', false),
                    Column::editor()->setUnquotedName('quantity')->setTypeName(Types::BIGINT)->create(),
                    $this->key($platform, 'from_place', true),
                    $this->key($platform, 'to_place', true),
                    Column::editor()->setUnquotedName('moved_at')->setTypeName(Types::BIGINT)->create(),
                    $this->key($platform, 'reference', true),
                    $this->text('note', Text::NOTE, true),
                )
                ->setPrimaryKeyConstraint($this->primaryKey('position'))
                ->setUniqueConstraints(UniqueConstraint::editor()->setUnquotedName($this->movements.'_id')->setUnquotedColumnNames('id')->create())
                ->setIndexes(
                    $this->index($this->movements.'_item', 'item', 'position'),
                    $this->index($this->movements.'_reference', 'reference', 'position'),
                )
                ->create(),
            Table::editor()
                ->setUnquotedName($this->loadLists)
                ->setColumns($this->identifier($platform, 'event'))
                ->setPrimaryKeyConstraint($this->primaryKey('event'))
                ->create(),
            Table::editor()
                ->setUnquotedName($this->loadLines)
                ->setColumns(
                    $this->identifier($platform, 'event'),
                    Column::editor()->setUnquotedName('line')->setTypeName(Types::INTEGER)->create(),
                    $this->key($platform, 'item', false),
                    Column::editor()->setUnquotedName('quantity')->setTypeName(Types::BIGINT)->create(),
                    $this->key($platform, 'from_container', true),
                )
                ->setPrimaryKeyConstraint($this->primaryKey('event', 'line'))
                ->create(),
        ];
    }

    /**
     * A column of identifiers or place keys, compared byte by byte, that may be empty or not.
     *
     * @param non-empty-string $name
     */
    private function key(AbstractPlatform $platform, string $name, bool $nullable): Column
    {
        return $this->bytes($platform, $name)->setNotNull(!$nullable)->create();
    }

    /**
     * A column of identifiers or place keys that a primary key holds: never empty, as every database makes it.
     *
     * @param non-empty-string $name
     */
    private function identifier(AbstractPlatform $platform, string $name): Column
    {
        return $this->bytes($platform, $name)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private function bytes(AbstractPlatform $platform, string $name): ColumnEditor
    {
        $column = Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength(self::KEY_LENGTH);

        if ($platform instanceof AbstractMySQLPlatform) {
            $column->setCharset('ascii')->setCollation('ascii_bin');
        } elseif ($platform instanceof PostgreSQLPlatform) {
            $column->setCollation('C');
        }

        return $column;
    }

    /**
     * @param non-empty-string $name
     */
    private function text(string $name, int $length, bool $nullable = false): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->setNotNull(!$nullable)->create();
    }

    /**
     * @param non-empty-string ...$columns
     */
    private function primaryKey(string ...$columns): PrimaryKeyConstraint
    {
        return PrimaryKeyConstraint::editor()->setUnquotedColumnNames(...$columns)->create();
    }

    /**
     * @param non-empty-string $name
     * @param non-empty-string ...$columns
     */
    private function index(string $name, string ...$columns): Index
    {
        return Index::editor()->setUnquotedName($name)->setUnquotedColumnNames(...$columns)->create();
    }
}
