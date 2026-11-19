<?php

declare(strict_types=1);

namespace Phil\Habits\Dbal;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Types;
use Phil\Habits\Exception\InvalidHabit;

/**
 * What both stores share of their tables: a name checked before it reaches SQL, and identifier columns compared byte
 * for byte, MySQL's default collation being blind to case.
 *
 * @internal
 */
final readonly class TableName
{
    /**
     * @return non-empty-string
     *
     * @throws InvalidHabit
     */
    public static function of(string $name): string
    {
        if (1 !== preg_match('/^[a-z_][a-z0-9_]{0,62}$/D', $name, $matches)) {
            throw InvalidHabit::because('A table name is up to 63 lowercase letters, digits or "_", not starting with a digit.');
        }

        return $matches[0];
    }

    /**
     * A column of identifiers: visible ASCII, 255 bytes at most, compared byte for byte.
     *
     * @param non-empty-string $name
     */
    public static function identifierColumn(string $name, AbstractPlatform $platform): Column
    {
        $column = Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength(255);

        if ($platform instanceof AbstractMySQLPlatform) {
            $column->setCharset('ascii')->setCollation('ascii_bin');
        }

        return $column->create();
    }
}
