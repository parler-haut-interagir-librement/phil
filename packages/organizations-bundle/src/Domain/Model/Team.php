<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Model;

use DateTimeImmutable;
use Phil\OrganizationsBundle\Domain\ValueObject\Moment;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationName;
use Phil\OrganizationsBundle\Domain\ValueObject\TeamId;
use Phil\Slug\Slug;

/**
 * A team inside an organization: its name, and its slug, unique within the organization. Its members are members of
 * the organization, each with roles of their own in the team.
 */
final class Team
{
    private function __construct(
        public readonly TeamId $id,
        public readonly OrganizationId $organizationId,
        public private(set) OrganizationName $name,
        public private(set) Slug $slug,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(TeamId $id, OrganizationId $organizationId, OrganizationName $name, Slug $slug, DateTimeImmutable $now): self
    {
        return new self($id, $organizationId, $name, $slug, Moment::of($now));
    }

    /**
     * A team read back from a store.
     */
    public static function restored(TeamId $id, OrganizationId $organizationId, OrganizationName $name, Slug $slug, DateTimeImmutable $createdAt): self
    {
        return new self($id, $organizationId, $name, $slug, $createdAt);
    }

    public function rename(OrganizationName $name, ?Slug $slug = null): void
    {
        $this->name = $name;
        $this->slug = $slug ?? $this->slug;
    }
}
