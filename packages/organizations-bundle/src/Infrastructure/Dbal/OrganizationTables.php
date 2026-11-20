<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Infrastructure\Dbal;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Phil\AccessLinks\TokenHasher;
use Phil\OrganizationsBundle\Domain\ValueObject\EmailDomain;
use Phil\OrganizationsBundle\Domain\ValueObject\Identifier;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationName;
use Phil\OrganizationsBundle\Domain\ValueObject\Roles;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitPath;
use Phil\Slug\Slug;

/**
 * The eight tables of the bundle, built for the platform of a connection:
 *
 * - `phil_organization`: one row per organization, its slug unique;
 * - `phil_organization_member`: one row per member of an organization, with their roles;
 * - `phil_organization_member_role`: one row per role of a member, what the owners are counted from;
 * - `phil_organization_team`: one row per team, its slug unique within its organization;
 * - `phil_organization_team_member`: one row per member of a team, with their team roles;
 * - `phil_organization_invitation`: one row per invitation, the keyed hash of its token unique;
 * - `phil_organization_unit`: one row per unit, with its parent and its path, indexed with its organization so that the
 *   units below a unit are one range of the index;
 * - `phil_organization_unit_member`: one row per member of a unit, with their unit roles.
 *
 * Every identifier and key column — identifiers, slugs, roles, domains, hashes — compares byte for byte: on MySQL and
 * MariaDB it is ASCII in the `ascii_bin` collation, a byte per character, and on PostgreSQL it takes the `C`
 * collation, so that `Acme` is never `acme`. Moments are Unix timestamps.
 */
final readonly class OrganizationTables
{
    public const string ORGANIZATION = 'phil_organization';

    public const string MEMBER = 'phil_organization_member';

    public const string MEMBER_ROLE = 'phil_organization_member_role';

    public const string TEAM = 'phil_organization_team';

    public const string TEAM_MEMBER = 'phil_organization_team_member';

    public const string INVITATION = 'phil_organization_invitation';

    public const string UNIT = 'phil_organization_unit';

    public const string UNIT_MEMBER = 'phil_organization_unit_member';

    public const array ALL = [self::UNIT_MEMBER, self::UNIT, self::INVITATION, self::TEAM_MEMBER, self::TEAM, self::MEMBER_ROLE, self::MEMBER, self::ORGANIZATION];

    /** The length of the roles written as one text: sixteen names of up to 64 characters and the spaces between. */
    private const int ROLES_LENGTH = Roles::MAX * 65;

    private function __construct(
        private AbstractPlatform $platform,
    ) {
    }

    public static function for(AbstractPlatform $platform): self
    {
        return new self($platform);
    }

    /**
     * Adds the tables a schema lacks: the one the ORM generates, through `OrganizationSchemaListener`, or one a
     * migration builds.
     */
    public function configure(Schema $schema): Schema
    {
        $editor = $schema->edit();

        foreach ($this->tables() as $table) {
            if (!$schema->hasTable($table->getObjectName()->toString())) {
                $editor->addTable($table);
            }
        }

        return $editor->create();
    }

    /**
     * The statements that add the tables a database lacks, for a migration: the tables of the units and their members
     * on a database of 0.1, every table on an empty one. Only tables are added; nothing is changed or dropped. The
     * comparator is the schema manager's of the connection.
     *
     * @return list<string>
     */
    public function upgrade(Schema $current, Comparator $comparator): array
    {
        return $this->platform->getAlterSchemaSQL($comparator->compareSchemas($current, $this->configure($current)));
    }

    /**
     * The statements that drop the tables of the units and their members, for the way back of a migration to 0.1.
     *
     * @return list<string>
     */
    public function downgrade(): array
    {
        return [$this->platform->getDropTableSQL(self::UNIT_MEMBER), $this->platform->getDropTableSQL(self::UNIT)];
    }

    /**
     * @return list<Table>
     */
    public function tables(): array
    {
        return [$this->organization(), $this->member(), $this->memberRole(), $this->team(), $this->teamMember(), $this->invitation(), $this->unit(), $this->unitMember()];
    }

    private function organization(): Table
    {
        return Table::editor()
            ->setUnquotedName(self::ORGANIZATION)
            ->setColumns(
                $this->key('id', Identifier::MAX_LENGTH)->create(),
                Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(OrganizationName::MAX_LENGTH)->create(),
                $this->key('slug', Slug::MAX_LENGTH)->create(),
                $this->key('email_domain', EmailDomain::MAX_LENGTH)->setNotNull(false)->create(),
                $this->moment('created_at')->create(),
                $this->moment('archived_at')->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName(self::ORGANIZATION.'_slug')->setUnquotedColumnNames('slug')->setType(IndexType::UNIQUE)->create(),
                Index::editor()->setUnquotedName(self::ORGANIZATION.'_email_domain')->setUnquotedColumnNames('email_domain')->create(),
            )
            ->create();
    }

    private function member(): Table
    {
        return Table::editor()
            ->setUnquotedName(self::MEMBER)
            ->setColumns(
                $this->key('organization_id', Identifier::MAX_LENGTH)->create(),
                $this->key('member_id', Identifier::MAX_LENGTH)->create(),
                $this->key('roles', self::ROLES_LENGTH)->create(),
                $this->key('method', 16)->create(),
                $this->moment('joined_at')->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('organization_id', 'member_id')->create())
            ->setIndexes(Index::editor()->setUnquotedName(self::MEMBER.'_member_id')->setUnquotedColumnNames('member_id', 'organization_id')->create())
            ->create();
    }

    private function memberRole(): Table
    {
        return Table::editor()
            ->setUnquotedName(self::MEMBER_ROLE)
            ->setColumns(
                $this->key('organization_id', Identifier::MAX_LENGTH)->create(),
                $this->key('role', 64)->create(),
                $this->key('member_id', Identifier::MAX_LENGTH)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('organization_id', 'role', 'member_id')->create())
            ->setIndexes(Index::editor()->setUnquotedName(self::MEMBER_ROLE.'_member_id')->setUnquotedColumnNames('member_id')->create())
            ->create();
    }

    private function team(): Table
    {
        return Table::editor()
            ->setUnquotedName(self::TEAM)
            ->setColumns(
                $this->key('id', Identifier::MAX_LENGTH)->create(),
                $this->key('organization_id', Identifier::MAX_LENGTH)->create(),
                Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(OrganizationName::MAX_LENGTH)->create(),
                $this->key('slug', Slug::MAX_LENGTH)->create(),
                $this->moment('created_at')->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(Index::editor()->setUnquotedName(self::TEAM.'_slug')->setUnquotedColumnNames('organization_id', 'slug')->setType(IndexType::UNIQUE)->create())
            ->create();
    }

    private function teamMember(): Table
    {
        return Table::editor()
            ->setUnquotedName(self::TEAM_MEMBER)
            ->setColumns(
                $this->key('team_id', Identifier::MAX_LENGTH)->create(),
                $this->key('member_id', Identifier::MAX_LENGTH)->create(),
                $this->key('organization_id', Identifier::MAX_LENGTH)->create(),
                $this->key('roles', self::ROLES_LENGTH)->create(),
                $this->moment('joined_at')->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('team_id', 'member_id')->create())
            ->setIndexes(Index::editor()->setUnquotedName(self::TEAM_MEMBER.'_member_id')->setUnquotedColumnNames('member_id', 'organization_id')->create())
            ->create();
    }

    private function invitation(): Table
    {
        return Table::editor()
            ->setUnquotedName(self::INVITATION)
            ->setColumns(
                $this->key('id', Identifier::MAX_LENGTH)->create(),
                $this->key('organization_id', Identifier::MAX_LENGTH)->create(),
                $this->key('token_hash', TokenHasher::LENGTH)->create(),
                $this->key('roles', self::ROLES_LENGTH)->create(),
                Column::editor()->setUnquotedName('max_uses')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('uses')->setTypeName(Types::INTEGER)->setDefaultValue(0)->create(),
                $this->moment('created_at')->create(),
                $this->moment('expires_at')->create(),
                $this->key('created_by', Identifier::MAX_LENGTH)->setNotNull(false)->create(),
                $this->moment('revoked_at')->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName(self::INVITATION.'_token_hash')->setUnquotedColumnNames('token_hash')->setType(IndexType::UNIQUE)->create(),
                Index::editor()->setUnquotedName(self::INVITATION.'_organization_id')->setUnquotedColumnNames('organization_id', 'created_at')->create(),
                Index::editor()->setUnquotedName(self::INVITATION.'_created_by')->setUnquotedColumnNames('created_by')->create(),
                Index::editor()->setUnquotedName(self::INVITATION.'_expires_at')->setUnquotedColumnNames('expires_at')->create(),
                Index::editor()->setUnquotedName(self::INVITATION.'_revoked_at')->setUnquotedColumnNames('revoked_at')->create(),
            )
            ->create();
    }

    private function unit(): Table
    {
        return Table::editor()
            ->setUnquotedName(self::UNIT)
            ->setColumns(
                $this->key('id', Identifier::MAX_LENGTH)->create(),
                $this->key('organization_id', Identifier::MAX_LENGTH)->create(),
                $this->key('parent_id', Identifier::MAX_LENGTH)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(OrganizationName::MAX_LENGTH)->create(),
                $this->key('path', UnitPath::MAX_LENGTH)->create(),
                Column::editor()->setUnquotedName('depth')->setTypeName(Types::SMALLINT)->create(),
                $this->moment('created_at')->create(),
                $this->moment('archived_at')->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName(self::UNIT.'_path')->setUnquotedColumnNames('organization_id', 'path')->create(),
                Index::editor()->setUnquotedName(self::UNIT.'_parent_id')->setUnquotedColumnNames('organization_id', 'parent_id')->create(),
            )
            ->create();
    }

    private function unitMember(): Table
    {
        return Table::editor()
            ->setUnquotedName(self::UNIT_MEMBER)
            ->setColumns(
                $this->key('unit_id', Identifier::MAX_LENGTH)->create(),
                $this->key('member_id', Identifier::MAX_LENGTH)->create(),
                $this->key('organization_id', Identifier::MAX_LENGTH)->create(),
                $this->key('roles', self::ROLES_LENGTH)->create(),
                $this->moment('joined_at')->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('unit_id', 'member_id')->create())
            ->setIndexes(Index::editor()->setUnquotedName(self::UNIT_MEMBER.'_member_id')->setUnquotedColumnNames('member_id', 'organization_id')->create())
            ->create();
    }

    /**
     * An ASCII column that compares byte for byte on the platform.
     *
     * @param non-empty-string $name
     */
    private function key(string $name, int $length): ColumnEditor
    {
        $column = Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length);

        if ($this->platform instanceof AbstractMySQLPlatform) {
            $column->setCharset('ascii')->setCollation('ascii_bin');
        } elseif ($this->platform instanceof PostgreSQLPlatform) {
            $column->setCollation('C');
        }

        return $column;
    }

    /**
     * @param non-empty-string $name
     */
    private function moment(string $name): ColumnEditor
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::BIGINT);
    }
}
