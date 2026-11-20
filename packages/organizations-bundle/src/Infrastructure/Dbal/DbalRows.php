<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Infrastructure\Dbal;

use DateTimeImmutable;
use Phil\OrganizationsBundle\Domain\Model\Invitation;
use Phil\OrganizationsBundle\Domain\Model\Membership;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Domain\Model\Team;
use Phil\OrganizationsBundle\Domain\Model\TeamMembership;
use Phil\OrganizationsBundle\Domain\Model\Unit;
use Phil\OrganizationsBundle\Domain\Model\UnitMembership;
use Phil\OrganizationsBundle\Domain\Status\JoinMethod;
use Phil\OrganizationsBundle\Domain\ValueObject\EmailDomain;
use Phil\OrganizationsBundle\Domain\ValueObject\InvitationId;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\Moment;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationName;
use Phil\OrganizationsBundle\Domain\ValueObject\Roles;
use Phil\OrganizationsBundle\Domain\ValueObject\TeamId;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitId;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitPath;
use Phil\Slug\Slug;
use UnexpectedValueException;

/**
 * The rows of the tables read back as the model, every column checked for its type.
 */
final readonly class DbalRows
{
    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function organization(array $row): Organization
    {
        return Organization::restored(
            OrganizationId::fromString(self::text($row['id'])),
            OrganizationName::fromString(self::text($row['name'])),
            Slug::fromString(self::text($row['slug'])),
            self::moment($row['created_at']),
            self::optionalMoment($row['archived_at']),
            null === $row['email_domain'] ? null : EmailDomain::fromString(self::text($row['email_domain'])),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function membership(array $row): Membership
    {
        return Membership::restored(
            OrganizationId::fromString(self::text($row['organization_id'])),
            MemberId::fromString(self::text($row['member_id'])),
            Roles::fromString(self::text($row['roles'])),
            JoinMethod::from(self::text($row['method'])),
            self::moment($row['joined_at']),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function team(array $row): Team
    {
        return Team::restored(
            TeamId::fromString(self::text($row['id'])),
            OrganizationId::fromString(self::text($row['organization_id'])),
            OrganizationName::fromString(self::text($row['name'])),
            Slug::fromString(self::text($row['slug'])),
            self::moment($row['created_at']),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function teamMembership(array $row): TeamMembership
    {
        return TeamMembership::restored(
            TeamId::fromString(self::text($row['team_id'])),
            MemberId::fromString(self::text($row['member_id'])),
            Roles::fromString(self::text($row['roles'])),
            self::moment($row['joined_at']),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function invitation(array $row): Invitation
    {
        return Invitation::restored(
            InvitationId::fromString(self::text($row['id'])),
            OrganizationId::fromString(self::text($row['organization_id'])),
            Roles::fromString(self::text($row['roles'])),
            self::integer($row['max_uses']),
            self::integer($row['uses']),
            self::moment($row['created_at']),
            self::moment($row['expires_at']),
            null === $row['created_by'] ? null : MemberId::fromString(self::text($row['created_by'])),
            self::optionalMoment($row['revoked_at']),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function unit(array $row): Unit
    {
        return Unit::restored(
            UnitId::fromString(self::text($row['id'])),
            OrganizationId::fromString(self::text($row['organization_id'])),
            OrganizationName::fromString(self::text($row['name'])),
            UnitPath::fromString(self::text($row['path'])),
            self::moment($row['created_at']),
            self::optionalMoment($row['archived_at']),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function unitMembership(array $row): UnitMembership
    {
        return UnitMembership::restored(
            UnitId::fromString(self::text($row['unit_id'])),
            OrganizationId::fromString(self::text($row['organization_id'])),
            MemberId::fromString(self::text($row['member_id'])),
            Roles::fromString(self::text($row['roles'])),
            self::moment($row['joined_at']),
        );
    }

    public static function text(mixed $value): string
    {
        if (!\is_string($value)) {
            throw new UnexpectedValueException('A text column of the organizations is not text.');
        }

        return $value;
    }

    public static function integer(mixed $value): int
    {
        if (!is_numeric($value)) {
            throw new UnexpectedValueException('A number column of the organizations is not a number.');
        }

        return (int) $value;
    }

    private static function moment(mixed $value): DateTimeImmutable
    {
        return Moment::fromTimestamp(self::integer($value));
    }

    private static function optionalMoment(mixed $value): ?DateTimeImmutable
    {
        return null === $value ? null : self::moment($value);
    }
}
