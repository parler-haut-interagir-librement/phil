<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Infrastructure\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\OrganizationsBundle\Application\Contract\OrganizationStore;
use Phil\OrganizationsBundle\Application\Contract\UnitStore;
use Phil\OrganizationsBundle\Application\Exception\SlugTaken;
use Phil\OrganizationsBundle\Domain\Model\Invitation;
use Phil\OrganizationsBundle\Domain\Model\Membership;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Model\Team;
use Phil\OrganizationsBundle\Domain\Model\TeamMembership;
use Phil\OrganizationsBundle\Domain\Model\Unit;
use Phil\OrganizationsBundle\Domain\Model\UnitMembership;
use Phil\OrganizationsBundle\Domain\Status\OrganizationStatus;
use Phil\OrganizationsBundle\Domain\ValueObject\EmailDomain;
use Phil\OrganizationsBundle\Domain\ValueObject\InvitationId;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\TeamId;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitId;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitPath;
use Phil\Slug\Slug;

/**
 * The organizations and their units in the eight tables of `OrganizationTables`, on a Doctrine DBAL connection:
 * PostgreSQL, MySQL, MariaDB or SQLite.
 *
 * An organization read with `$lock` is read `FOR UPDATE` on PostgreSQL, MySQL and MariaDB, so that two changes of one
 * organization run one after the other; SQLite, which knows no `FOR UPDATE`, writes one transaction at a time, and
 * refuses the second writer rather than lose its change. A use of an invitation is one
 * conditional `UPDATE` whose row count is checked: of two members taking its last use at once, one counts it. The units
 * below a unit are read, moved and archived through one range of the index on the organization and the path — from the
 * unit's path to the bound `UnitPath::upperBound()` gives —, a move rewriting every path below in one `UPDATE`. Every
 * statement takes its values as parameters, but for the position of a substring, a number the store counts.
 */
final readonly class DbalOrganizationStore implements OrganizationStore, UnitStore
{
    private const string ORGANIZATION_COLUMNS = 'id, name, slug, email_domain, created_at, archived_at';

    private const string MEMBER_COLUMNS = 'organization_id, member_id, roles, method, joined_at';

    private const string TEAM_COLUMNS = 'id, organization_id, name, slug, created_at';

    private const string TEAM_MEMBER_COLUMNS = 'team_id, member_id, roles, joined_at';

    private const string UNIT_COLUMNS = 'id, organization_id, name, path, created_at, archived_at';

    private const string UNIT_MEMBER_COLUMNS = 'unit_id, organization_id, member_id, roles, joined_at';

    private const string WITHIN = 'organization_id = ? AND path >= ? AND path < ?';

    private const string INVITATION_COLUMNS = 'id, organization_id, roles, max_uses, uses, created_at, expires_at, created_by, revoked_at';

    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * Adds the tables to a schema that lacks them, for a migration.
     */
    public function configureSchema(Schema $schema): Schema
    {
        return OrganizationTables::for($this->connection->getDatabasePlatform())->configure($schema);
    }

    #[Override]
    public function transactional(callable $work): mixed
    {
        return $this->connection->transactional(static fn (): mixed => $work());
    }

    #[Override]
    public function organization(OrganizationId $id, bool $lock = false): ?Organization
    {
        $query = $this->connection->createQueryBuilder()
            ->select(self::ORGANIZATION_COLUMNS)
            ->from(OrganizationTables::ORGANIZATION)
            ->where('id = ?')
            ->setParameter(0, $id->value);

        if ($lock && !$this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $query->forUpdate();
        }

        $row = $query->fetchAssociative();

        return false === $row ? null : DbalRows::organization($row);
    }

    #[Override]
    public function organizationBySlug(Slug $slug): ?Organization
    {
        return $this->selectOrganizations('WHERE slug = ?', [$slug->value])[0] ?? null;
    }

    #[Override]
    public function saveOrganization(Organization $organization): void
    {
        $values = [
            'name' => $organization->name->value,
            'slug' => $organization->slug->value,
            'email_domain' => $organization->emailDomain?->value,
            'archived_at' => $organization->archivedAt?->getTimestamp(),
        ];

        try {
            if ($this->exists(OrganizationTables::ORGANIZATION, ['id' => $organization->id->value])) {
                $this->connection->update(OrganizationTables::ORGANIZATION, $values, ['id' => $organization->id->value]);

                return;
            }

            $this->connection->insert(OrganizationTables::ORGANIZATION, ['id' => $organization->id->value, ...$values, 'created_at' => $organization->createdAt->getTimestamp()]);
        } catch (UniqueConstraintViolationException) {
            throw SlugTaken::of($organization->slug->value);
        }
    }

    #[Override]
    public function organizations(?OrganizationStatus $status, int $limit, ?string $afterSlug = null): array
    {
        $conditions = [];
        $parameters = [];

        if (OrganizationStatus::Active === $status) {
            $conditions[] = 'archived_at IS NULL';
        } elseif (OrganizationStatus::Archived === $status) {
            $conditions[] = 'archived_at IS NOT NULL';
        }

        if (null !== $afterSlug) {
            $conditions[] = 'slug > ?';
            $parameters[] = $afterSlug;
        }

        return $this->selectOrganizations(([] === $conditions ? '' : 'WHERE '.implode(' AND ', $conditions)).' ORDER BY slug', $parameters, $limit);
    }

    #[Override]
    public function organizationsWithDomain(EmailDomain $domain): array
    {
        return $this->selectOrganizations('WHERE email_domain = ? AND archived_at IS NULL ORDER BY slug', [$domain->value], self::MAX_PAGE);
    }

    #[Override]
    public function membership(OrganizationId $organizationId, MemberId $memberId): ?Membership
    {
        return $this->selectMemberships('WHERE organization_id = ? AND member_id = ?', [$organizationId->value, $memberId->value])[0] ?? null;
    }

    #[Override]
    public function memberships(OrganizationId $organizationId, int $limit, ?string $afterMember = null): array
    {
        return null === $afterMember
            ? $this->selectMemberships('WHERE organization_id = ? ORDER BY member_id', [$organizationId->value], $limit)
            : $this->selectMemberships('WHERE organization_id = ? AND member_id > ? ORDER BY member_id', [$organizationId->value, $afterMember], $limit);
    }

    #[Override]
    public function membershipsOf(MemberId $memberId): array
    {
        return $this->selectMemberships('WHERE member_id = ? ORDER BY organization_id', [$memberId->value]);
    }

    #[Override]
    public function saveMembership(Membership $membership): void
    {
        $key = ['organization_id' => $membership->organizationId->value, 'member_id' => $membership->memberId->value];

        if ($this->exists(OrganizationTables::MEMBER, $key)) {
            $this->connection->update(OrganizationTables::MEMBER, ['roles' => $membership->roles->toString()], $key);
            $this->connection->delete(OrganizationTables::MEMBER_ROLE, $key);
        } else {
            $this->connection->insert(OrganizationTables::MEMBER, [...$key, 'roles' => $membership->roles->toString(), 'method' => $membership->method->value, 'joined_at' => $membership->joinedAt->getTimestamp()]);
        }

        foreach ($membership->roles->names as $role) {
            $this->connection->insert(OrganizationTables::MEMBER_ROLE, [...$key, 'role' => $role]);
        }
    }

    #[Override]
    public function deleteMembership(OrganizationId $organizationId, MemberId $memberId): bool
    {
        $key = ['organization_id' => $organizationId->value, 'member_id' => $memberId->value];
        $this->connection->delete(OrganizationTables::UNIT_MEMBER, $key);
        $this->connection->delete(OrganizationTables::TEAM_MEMBER, $key);
        $this->connection->delete(OrganizationTables::MEMBER_ROLE, $key);

        return (int) $this->connection->delete(OrganizationTables::MEMBER, $key) > 0;
    }

    #[Override]
    public function countMembers(OrganizationId $organizationId): int
    {
        return $this->count(\sprintf('SELECT COUNT(*) FROM %s WHERE organization_id = ?', OrganizationTables::MEMBER), [$organizationId->value]);
    }

    #[Override]
    public function countWithRole(OrganizationId $organizationId, string $role): int
    {
        return $this->count(\sprintf('SELECT COUNT(*) FROM %s WHERE organization_id = ? AND role = ?', OrganizationTables::MEMBER_ROLE), [$organizationId->value, $role]);
    }

    #[Override]
    public function organizationsWithFewer(string $role, int $below, int $limit): array
    {
        $sql = $this->connection->getDatabasePlatform()->modifyLimitQuery(\sprintf(
            'SELECT o.id, COUNT(r.member_id) AS holders FROM %s o LEFT JOIN %s r ON r.organization_id = o.id AND r.role = ? GROUP BY o.id HAVING COUNT(r.member_id) < ? ORDER BY holders, o.id',
            OrganizationTables::ORGANIZATION,
            OrganizationTables::MEMBER_ROLE,
        ), $limit);
        $found = [];

        foreach ($this->connection->fetchAllAssociative($sql, [$role, $below], [ParameterType::STRING, ParameterType::INTEGER]) as $row) {
            $found[DbalRows::text($row['id'])] = DbalRows::integer($row['holders']);
        }

        return $found;
    }

    #[Override]
    public function team(TeamId $id): ?Team
    {
        return $this->selectTeams('WHERE id = ?', [$id->value])[0] ?? null;
    }

    #[Override]
    public function teamBySlug(OrganizationId $organizationId, Slug $slug): ?Team
    {
        return $this->selectTeams('WHERE organization_id = ? AND slug = ?', [$organizationId->value, $slug->value])[0] ?? null;
    }

    #[Override]
    public function teams(OrganizationId $organizationId): array
    {
        return $this->selectTeams('WHERE organization_id = ? ORDER BY slug', [$organizationId->value]);
    }

    #[Override]
    public function saveTeam(Team $team): void
    {
        $values = ['name' => $team->name->value, 'slug' => $team->slug->value];

        try {
            if ($this->exists(OrganizationTables::TEAM, ['id' => $team->id->value])) {
                $this->connection->update(OrganizationTables::TEAM, $values, ['id' => $team->id->value]);

                return;
            }

            $this->connection->insert(OrganizationTables::TEAM, ['id' => $team->id->value, 'organization_id' => $team->organizationId->value, ...$values, 'created_at' => $team->createdAt->getTimestamp()]);
        } catch (UniqueConstraintViolationException) {
            throw SlugTaken::of($team->slug->value);
        }
    }

    #[Override]
    public function deleteTeam(TeamId $id): bool
    {
        $this->connection->delete(OrganizationTables::TEAM_MEMBER, ['team_id' => $id->value]);

        return (int) $this->connection->delete(OrganizationTables::TEAM, ['id' => $id->value]) > 0;
    }

    #[Override]
    public function teamMembership(TeamId $teamId, MemberId $memberId): ?TeamMembership
    {
        return $this->selectTeamMemberships('WHERE team_id = ? AND member_id = ?', [$teamId->value, $memberId->value])[0] ?? null;
    }

    #[Override]
    public function teamMemberships(TeamId $teamId): array
    {
        return $this->selectTeamMemberships('WHERE team_id = ? ORDER BY member_id', [$teamId->value]);
    }

    #[Override]
    public function teamMembershipsOf(MemberId $memberId, ?OrganizationId $organizationId = null): array
    {
        return $organizationId instanceof OrganizationId
            ? $this->selectTeamMemberships('WHERE member_id = ? AND organization_id = ? ORDER BY team_id', [$memberId->value, $organizationId->value])
            : $this->selectTeamMemberships('WHERE member_id = ? ORDER BY team_id', [$memberId->value]);
    }

    #[Override]
    public function saveTeamMembership(TeamMembership $membership): void
    {
        $key = ['team_id' => $membership->teamId->value, 'member_id' => $membership->memberId->value];

        if ($this->exists(OrganizationTables::TEAM_MEMBER, $key)) {
            $this->connection->update(OrganizationTables::TEAM_MEMBER, ['roles' => $membership->roles->toString()], $key);

            return;
        }

        $organizationId = $this->connection->fetchOne(\sprintf('SELECT organization_id FROM %s WHERE id = ?', OrganizationTables::TEAM), [$membership->teamId->value]);
        $this->connection->insert(OrganizationTables::TEAM_MEMBER, [...$key, 'organization_id' => DbalRows::text($organizationId), 'roles' => $membership->roles->toString(), 'joined_at' => $membership->joinedAt->getTimestamp()]);
    }

    #[Override]
    public function deleteTeamMembership(TeamId $teamId, MemberId $memberId): bool
    {
        return (int) $this->connection->delete(OrganizationTables::TEAM_MEMBER, ['team_id' => $teamId->value, 'member_id' => $memberId->value]) > 0;
    }

    #[Override]
    public function addInvitation(Invitation $invitation, string $tokenHash): void
    {
        $this->connection->insert(OrganizationTables::INVITATION, [
            'id' => $invitation->id->value,
            'organization_id' => $invitation->organizationId->value,
            'token_hash' => $tokenHash,
            'roles' => $invitation->roles->toString(),
            'max_uses' => $invitation->maxUses,
            'uses' => $invitation->uses,
            'created_at' => $invitation->createdAt->getTimestamp(),
            'expires_at' => $invitation->expiresAt->getTimestamp(),
            'created_by' => $invitation->createdBy?->value,
            'revoked_at' => $invitation->revokedAt?->getTimestamp(),
        ]);
    }

    #[Override]
    public function invitation(InvitationId $id): ?Invitation
    {
        return $this->selectInvitations('WHERE id = ?', [$id->value])[0] ?? null;
    }

    #[Override]
    public function invitationByHash(array $hashes): ?Invitation
    {
        return $this->selectInvitations('WHERE token_hash IN (?)', [$hashes], [ArrayParameterType::STRING])[0] ?? null;
    }

    #[Override]
    public function invitations(OrganizationId $organizationId): array
    {
        return $this->selectInvitations('WHERE organization_id = ? ORDER BY created_at DESC, id DESC', [$organizationId->value]);
    }

    #[Override]
    public function useInvitation(InvitationId $id, DateTimeImmutable $now): bool
    {
        return 1 === (int) $this->connection->executeStatement(
            \sprintf('UPDATE %s SET uses = uses + 1 WHERE id = ? AND revoked_at IS NULL AND uses < max_uses AND expires_at > ?', OrganizationTables::INVITATION),
            [$id->value, $now->getTimestamp()],
            [ParameterType::STRING, ParameterType::INTEGER],
        );
    }

    #[Override]
    public function revokeInvitation(InvitationId $id, DateTimeImmutable $now): bool
    {
        return 1 === (int) $this->connection->executeStatement(
            \sprintf('UPDATE %s SET revoked_at = ? WHERE id = ? AND revoked_at IS NULL', OrganizationTables::INVITATION),
            [$now->getTimestamp(), $id->value],
            [ParameterType::INTEGER, ParameterType::STRING],
        );
    }

    #[Override]
    public function purgeInvitations(DateTimeImmutable $before): int
    {
        return (int) $this->connection->executeStatement(
            \sprintf('DELETE FROM %s WHERE expires_at <= ? OR revoked_at <= ?', OrganizationTables::INVITATION),
            [$before->getTimestamp(), $before->getTimestamp()],
            [ParameterType::INTEGER, ParameterType::INTEGER],
        );
    }

    #[Override]
    public function invitationsCreatedBy(MemberId $memberId): array
    {
        return $this->selectInvitations('WHERE created_by = ? ORDER BY created_at DESC, id DESC', [$memberId->value]);
    }

    #[Override]
    public function forgetInvitationCreator(MemberId $memberId): int
    {
        return (int) $this->connection->update(OrganizationTables::INVITATION, ['created_by' => null], ['created_by' => $memberId->value]);
    }

    #[Override]
    public function unit(UnitId $id): ?Unit
    {
        return $this->selectUnits('WHERE id = ?', [$id->value])[0] ?? null;
    }

    #[Override]
    public function saveUnit(Unit $unit): void
    {
        $values = ['name' => $unit->name->value, 'archived_at' => $unit->archivedAt?->getTimestamp()];

        if ($this->exists(OrganizationTables::UNIT, ['id' => $unit->id->value])) {
            $this->connection->update(OrganizationTables::UNIT, $values, ['id' => $unit->id->value]);

            return;
        }

        $this->connection->insert(OrganizationTables::UNIT, [
            'id' => $unit->id->value,
            'organization_id' => $unit->organizationId->value,
            'parent_id' => $unit->parentId()?->value,
            'path' => $unit->path->toString(),
            'depth' => $unit->path->depth(),
            'created_at' => $unit->createdAt->getTimestamp(),
            ...$values,
        ]);
    }

    #[Override]
    public function moveUnit(Unit $unit, UnitPath $from): void
    {
        $platform = $this->connection->getDatabasePlatform();
        // The position is a number the store counts, written in the statement: PostgreSQL would read a parameter there
        // as the pattern of SUBSTRING(text FROM pattern).
        $this->connection->executeStatement(
            \sprintf(
                'UPDATE %s SET path = %s, depth = depth + ? WHERE %s',
                OrganizationTables::UNIT,
                $platform->getConcatExpression('?', $platform->getSubstringExpression('path', (string) (\strlen($from->toString()) + 1))),
                self::WITHIN,
            ),
            [$unit->path->toString(), $unit->path->depth() - $from->depth(), $unit->organizationId->value, $from->toString(), $from->upperBound()],
            [ParameterType::STRING, ParameterType::INTEGER, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING],
        );
        $this->connection->update(OrganizationTables::UNIT, ['parent_id' => $unit->parentId()?->value], ['id' => $unit->id->value]);
    }

    #[Override]
    public function units(OrganizationId $organizationId, ?UnitPath $within = null, bool $withArchived = true): array
    {
        [$condition, $parameters] = $within instanceof UnitPath
            ? [self::WITHIN, [$organizationId->value, $within->toString(), $within->upperBound()]]
            : ['organization_id = ?', [$organizationId->value]];

        return $this->selectUnits('WHERE '.$condition.($withArchived ? '' : ' AND archived_at IS NULL').' ORDER BY path', $parameters);
    }

    #[Override]
    public function children(OrganizationId $organizationId, ?UnitId $parentId, bool $withArchived = true): array
    {
        [$condition, $parameters] = $parentId instanceof UnitId
            ? ['organization_id = ? AND parent_id = ?', [$organizationId->value, $parentId->value]]
            : ['organization_id = ? AND parent_id IS NULL', [$organizationId->value]];

        return $this->selectUnits('WHERE '.$condition.($withArchived ? '' : ' AND archived_at IS NULL').' ORDER BY path', $parameters);
    }

    #[Override]
    public function unitsById(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->selectUnits('WHERE id IN (?) ORDER BY path', [array_map(static fn (UnitId $id): string => $id->value, $ids)], [ArrayParameterType::STRING]);
    }

    #[Override]
    public function countUnits(OrganizationId $organizationId): int
    {
        return $this->count(\sprintf('SELECT COUNT(*) FROM %s WHERE organization_id = ?', OrganizationTables::UNIT), [$organizationId->value]);
    }

    #[Override]
    public function deepest(OrganizationId $organizationId, UnitPath $within): int
    {
        return DbalRows::integer($this->connection->fetchOne(
            \sprintf('SELECT COALESCE(MAX(depth), 0) FROM %s WHERE %s', OrganizationTables::UNIT, self::WITHIN),
            [$organizationId->value, $within->toString(), $within->upperBound()],
        ));
    }

    #[Override]
    public function archiveUnits(OrganizationId $organizationId, UnitPath $within, DateTimeImmutable $now): array
    {
        $parameters = [$organizationId->value, $within->toString(), $within->upperBound()];
        $ids = $this->unitIds(self::WITHIN.' AND archived_at IS NULL', $parameters, []);
        $this->connection->executeStatement(
            \sprintf('UPDATE %s SET archived_at = ? WHERE %s AND archived_at IS NULL', OrganizationTables::UNIT, self::WITHIN),
            [$now->getTimestamp(), ...$parameters],
            [ParameterType::INTEGER, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING],
        );

        return $ids;
    }

    #[Override]
    public function restoreUnits(OrganizationId $organizationId, UnitPath $within, DateTimeImmutable $archivedAt): array
    {
        $parameters = [$organizationId->value, $within->toString(), $within->upperBound(), $archivedAt->getTimestamp()];
        $types = [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER];
        $ids = $this->unitIds(self::WITHIN.' AND archived_at = ?', $parameters, $types);
        $this->connection->executeStatement(\sprintf('UPDATE %s SET archived_at = NULL WHERE %s AND archived_at = ?', OrganizationTables::UNIT, self::WITHIN), $parameters, $types);

        return $ids;
    }

    #[Override]
    public function unitMembership(UnitId $unitId, MemberId $memberId): ?UnitMembership
    {
        return $this->selectUnitMemberships('WHERE unit_id = ? AND member_id = ?', [$unitId->value, $memberId->value])[0] ?? null;
    }

    #[Override]
    public function unitMemberships(UnitId $unitId, int $limit, ?string $afterMember = null): array
    {
        return null === $afterMember
            ? $this->selectUnitMemberships('WHERE unit_id = ? ORDER BY member_id', [$unitId->value], [], $limit)
            : $this->selectUnitMemberships('WHERE unit_id = ? AND member_id > ? ORDER BY member_id', [$unitId->value, $afterMember], [], $limit);
    }

    #[Override]
    public function unitMembershipsOf(MemberId $memberId, ?OrganizationId $organizationId = null): array
    {
        return $organizationId instanceof OrganizationId
            ? $this->selectUnitMemberships('WHERE member_id = ? AND organization_id = ? ORDER BY unit_id', [$memberId->value, $organizationId->value])
            : $this->selectUnitMemberships('WHERE member_id = ? ORDER BY unit_id', [$memberId->value]);
    }

    #[Override]
    public function unitMembershipsAmong(array $unitIds, MemberId $memberId): array
    {
        if ([] === $unitIds) {
            return [];
        }

        return $this->selectUnitMemberships(
            'WHERE member_id = ? AND unit_id IN (?) ORDER BY unit_id',
            [$memberId->value, array_map(static fn (UnitId $id): string => $id->value, $unitIds)],
            [ParameterType::STRING, ArrayParameterType::STRING],
        );
    }

    #[Override]
    public function membersWithin(OrganizationId $organizationId, UnitPath $within, int $limit, ?string $afterMember = null): array
    {
        $sql = \sprintf(
            'SELECT DISTINCT m.member_id FROM %s m INNER JOIN %s u ON u.id = m.unit_id WHERE u.organization_id = ? AND u.path >= ? AND u.path < ?%s ORDER BY m.member_id',
            OrganizationTables::UNIT_MEMBER,
            OrganizationTables::UNIT,
            null === $afterMember ? '' : ' AND m.member_id > ?',
        );
        $parameters = [$organizationId->value, $within->toString(), $within->upperBound()];

        if (null !== $afterMember) {
            $parameters[] = $afterMember;
        }

        return array_map(
            static fn (mixed $member): MemberId => MemberId::fromString(DbalRows::text($member)),
            $this->connection->fetchFirstColumn($this->connection->getDatabasePlatform()->modifyLimitQuery($sql, $limit), $parameters),
        );
    }

    #[Override]
    public function saveUnitMembership(UnitMembership $membership): void
    {
        $key = ['unit_id' => $membership->unitId->value, 'member_id' => $membership->memberId->value];

        if ($this->exists(OrganizationTables::UNIT_MEMBER, $key)) {
            $this->connection->update(OrganizationTables::UNIT_MEMBER, ['roles' => $membership->roles->toString()], $key);

            return;
        }

        $this->connection->insert(OrganizationTables::UNIT_MEMBER, [...$key, 'organization_id' => $membership->organizationId->value, 'roles' => $membership->roles->toString(), 'joined_at' => $membership->joinedAt->getTimestamp()]);
    }

    #[Override]
    public function deleteUnitMembership(UnitId $unitId, MemberId $memberId): bool
    {
        return (int) $this->connection->delete(OrganizationTables::UNIT_MEMBER, ['unit_id' => $unitId->value, 'member_id' => $memberId->value]) > 0;
    }

    /**
     * @param array<string, string> $key
     */
    private function exists(string $table, array $key): bool
    {
        $conditions = implode(' AND ', array_map(static fn (string $column): string => $column.' = ?', array_keys($key)));

        return false !== $this->connection->fetchOne(\sprintf('SELECT 1 FROM %s WHERE %s', $table, $conditions), array_values($key));
    }

    /**
     * @param list<mixed> $parameters
     */
    private function count(string $sql, array $parameters): int
    {
        return DbalRows::integer($this->connection->fetchOne($sql, $parameters));
    }

    /**
     * @param list<mixed>                            $parameters
     * @param list<ParameterType|ArrayParameterType> $types
     *
     * @return list<array<string, mixed>>
     */
    private function rows(string $columns, string $table, string $condition, array $parameters, ?int $limit, array $types = []): array
    {
        $sql = \sprintf('SELECT %s FROM %s %s', $columns, $table, $condition);

        if (null !== $limit) {
            $sql = $this->connection->getDatabasePlatform()->modifyLimitQuery($sql, $limit);
        }

        return $this->connection->fetchAllAssociative($sql, $parameters, $types);
    }

    /**
     * @param list<mixed> $parameters
     *
     * @return list<Organization>
     */
    private function selectOrganizations(string $condition, array $parameters, ?int $limit = null): array
    {
        return array_map(DbalRows::organization(...), $this->rows(self::ORGANIZATION_COLUMNS, OrganizationTables::ORGANIZATION, $condition, $parameters, $limit));
    }

    /**
     * @param list<mixed> $parameters
     *
     * @return list<Membership>
     */
    private function selectMemberships(string $condition, array $parameters, ?int $limit = null): array
    {
        return array_map(DbalRows::membership(...), $this->rows(self::MEMBER_COLUMNS, OrganizationTables::MEMBER, $condition, $parameters, $limit));
    }

    /**
     * @param list<mixed> $parameters
     *
     * @return list<Team>
     */
    private function selectTeams(string $condition, array $parameters): array
    {
        return array_map(DbalRows::team(...), $this->rows(self::TEAM_COLUMNS, OrganizationTables::TEAM, $condition, $parameters, null));
    }

    /**
     * @param list<mixed> $parameters
     *
     * @return list<TeamMembership>
     */
    private function selectTeamMemberships(string $condition, array $parameters): array
    {
        return array_map(DbalRows::teamMembership(...), $this->rows(self::TEAM_MEMBER_COLUMNS, OrganizationTables::TEAM_MEMBER, $condition, $parameters, null));
    }

    /**
     * @param list<mixed>                            $parameters
     * @param list<ParameterType|ArrayParameterType> $types
     *
     * @return list<Invitation>
     */
    private function selectInvitations(string $condition, array $parameters, array $types = []): array
    {
        return array_map(DbalRows::invitation(...), $this->rows(self::INVITATION_COLUMNS, OrganizationTables::INVITATION, $condition, $parameters, null, $types));
    }

    /**
     * @param list<mixed>                            $parameters
     * @param list<ParameterType|ArrayParameterType> $types
     *
     * @return list<Unit>
     */
    private function selectUnits(string $condition, array $parameters, array $types = []): array
    {
        return array_map(DbalRows::unit(...), $this->rows(self::UNIT_COLUMNS, OrganizationTables::UNIT, $condition, $parameters, null, $types));
    }

    /**
     * @param list<mixed>                            $parameters
     * @param list<ParameterType|ArrayParameterType> $types
     *
     * @return list<UnitId> sorted by path
     */
    private function unitIds(string $condition, array $parameters, array $types): array
    {
        return array_map(
            static fn (mixed $id): UnitId => UnitId::fromString(DbalRows::text($id)),
            $this->connection->fetchFirstColumn(\sprintf('SELECT id FROM %s WHERE %s ORDER BY path', OrganizationTables::UNIT, $condition), $parameters, $types),
        );
    }

    /**
     * @param list<mixed>                            $parameters
     * @param list<ParameterType|ArrayParameterType> $types
     *
     * @return list<UnitMembership>
     */
    private function selectUnitMemberships(string $condition, array $parameters, array $types = [], ?int $limit = null): array
    {
        return array_map(DbalRows::unitMembership(...), $this->rows(self::UNIT_MEMBER_COLUMNS, OrganizationTables::UNIT_MEMBER, $condition, $parameters, $limit, $types));
    }
}
