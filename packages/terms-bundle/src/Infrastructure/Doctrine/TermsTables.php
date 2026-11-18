<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Phil\TermsBundle\Domain\Identifiers;
use Phil\TermsBundle\Domain\ValueObject\Acceptance;

/**
 * The two tables of the bundle, added to the schema Doctrine generates so that the application's migrations create
 * them:
 *
 * - `phil_terms_version`: the versions of the policies, one row each, the texts of the locales as a JSON object and
 *   the licence of the contributions, when the version names one;
 * - `phil_terms_acceptance`: the acceptances, one row each, only ever added, found by member, policy and version —
 *   the question the gate asks on every request.
 *
 * **Members and policies compare byte by byte.** The gate asks whether a member accepted a policy's version: given the
 * platform, MySQL and MariaDB store the member and the policy in `utf8mb4` with the `utf8mb4_bin` collation, so that an
 * acceptance by `Ann` never lets `ann` through, as a server's default collation (`utf8mb4_0900_ai_ci`) would.
 * PostgreSQL's default collations are deterministic and SQLite compares bytes: their columns keep the default.
 */
final class TermsTables
{
    public const string VERSIONS = 'phil_terms_version';

    public const string ACCEPTANCES = 'phil_terms_acceptance';

    private function __construct()
    {
    }

    /**
     * The schema with the two tables added, those it already has left as they are. Without a platform, the columns
     * keep the connection's default collation: the schema listener always gives the platform of its connection.
     */
    public static function configure(Schema $schema, ?AbstractPlatform $platform = null): Schema
    {
        $binary = $platform instanceof AbstractMySQLPlatform;
        $editor = $schema->edit();

        if (!$schema->hasTable(self::VERSIONS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::VERSIONS)
                ->addColumn(self::id())
                ->addColumn(self::string('policy', 64, $binary))
                ->addColumn(self::integer('number'))
                ->addColumn(self::string('format', 8))
                ->addColumn(Column::editor()->setUnquotedName('texts')->setTypeName(Types::TEXT)->setNotNull(true)->create())
                ->addColumn(self::moment('published_at'))
                ->addColumn(self::moment('effective_at'))
                ->addColumn(Column::editor()->setUnquotedName('material')->setTypeName(Types::BOOLEAN)->setNotNull(true)->create())
                ->addColumn(Column::editor()->setUnquotedName('licence')->setTypeName(Types::STRING)->setLength(Identifiers::MAX_LICENCE_LENGTH)->setNotNull(false)->create())
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                ->addIndex(Index::editor()->setUnquotedName('uniq_phil_terms_version_number')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('policy', 'number')->create())
                ->create());
        }

        if (!$schema->hasTable(self::ACCEPTANCES)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::ACCEPTANCES)
                ->addColumn(self::id())
                ->addColumn(self::string('member', Identifiers::MAX_MEMBER_LENGTH, $binary))
                ->addColumn(self::string('policy', 64, $binary))
                ->addColumn(self::integer('version'))
                ->addColumn(self::moment('accepted_at'))
                ->addColumn(self::string('channel', 16))
                ->addColumn(Column::editor()->setUnquotedName('network')->setTypeName(Types::STRING)->setLength(Acceptance::MAX_NETWORK_LENGTH)->setNotNull(false)->create())
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                ->addIndex(Index::editor()->setUnquotedName('idx_phil_terms_acceptance_member')->setUnquotedColumnNames('member', 'policy', 'version')->create())
                ->create());
        }

        return $editor->create();
    }

    private static function id(): Column
    {
        return Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->setAutoincrement(true)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function string(string $name, int $length, bool $binary = false): Column
    {
        $column = Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->setNotNull(true);

        if ($binary) {
            $column->setCharset('utf8mb4')->setCollation('utf8mb4_bin');
        }

        return $column->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function integer(string $name): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::INTEGER)->setNotNull(true)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function moment(string $name): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::DATETIME_UTC_IMMUTABLE)->setNotNull(true)->create();
    }
}
