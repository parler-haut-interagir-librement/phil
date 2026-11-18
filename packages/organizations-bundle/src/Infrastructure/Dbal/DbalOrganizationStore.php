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
use Phil\OrganizationsBundle\Application\Exception\SlugTaken;
use Phil\OrganizationsBundle\Domain\Model\Invitation;
use Phil\OrganizationsBundle\Domain\Model\Membership;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Model\Team;
use Phil\OrganizationsBundle\Domain\Model\TeamMembership;
use Phil\OrganizationsBundle\Domain\Status\OrganizationStatus;
use Phil\OrganizationsBundle\Domain\ValueObject\EmailDomain;
use Phil\OrganizationsBundle\Domain\ValueObject\InvitationId;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\TeamId;
use Phil\Slug\Slug;

/**
 * The organizations in the six tables of `OrganizationTables`, on a Doctrine DBAL connection: PostgreSQL, MySQL,
 * MariaDB or SQLite.
 *
 * An organization read with `$lock` is read `FOR UPDATE` on PostgreSQL, MySQL and MariaDB, so that two changes of one
 * organization run one after the other; SQLite, which knows no `FOR UPDATE`, writes one transaction at a time, and
 * refuses the second writer rather than lose its change. A use of an invitation is one
 * conditional `UPDATE` whose row count is checked: of two members taking its last use at once, one counts it. Every
 * statement takes its values as parameters.
 */
final readonly class DbalOrganizationStore implements OrganizationStore
{
    private const string ORGANIZATION_COLUMNS = 'id, name, slug, email_domain, created_at, archived_at';

    private const string MEMBER_COLUMNS = 'organization_id, member_id, roles, method, joined_at';

    private const string TEAM_COLUMNS = 'id, organization_id, name, slug, created_at';

    private const string TEAM_MEMBER_COLUMNS = 'team_id, member_id, roles, joined_at';

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
}
