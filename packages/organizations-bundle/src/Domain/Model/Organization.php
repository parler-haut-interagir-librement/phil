<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Model;

use DateTimeImmutable;
use Phil\OrganizationsBundle\Domain\Status\OrganizationStatus;
use Phil\OrganizationsBundle\Domain\ValueObject\EmailDomain;
use Phil\OrganizationsBundle\Domain\ValueObject\Moment;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationName;
use Phil\Slug\Slug;

/**
 * An organization: its name, its slug — unique among organizations —, whether it is active or archived, and the email
 * domain it verified, when it has one. Its members, teams and invitations are kept beside it, by its identifier.
 */
final class Organization
{
    private function __construct(
        public readonly OrganizationId $id,
        public private(set) OrganizationName $name,
        public private(set) Slug $slug,
        public private(set) OrganizationStatus $status,
        public readonly DateTimeImmutable $createdAt,
        public private(set) ?DateTimeImmutable $archivedAt,
        public private(set) ?EmailDomain $emailDomain,
    ) {
    }

    public static function create(OrganizationId $id, OrganizationName $name, Slug $slug, DateTimeImmutable $now): self
    {
        return new self($id, $name, $slug, OrganizationStatus::Active, Moment::of($now), null, null);
    }

    /**
     * An organization read back from a store.
     */
    public static function restored(OrganizationId $id, OrganizationName $name, Slug $slug, DateTimeImmutable $createdAt, ?DateTimeImmutable $archivedAt, ?EmailDomain $emailDomain): self
    {
        return new self($id, $name, $slug, $archivedAt instanceof DateTimeImmutable ? OrganizationStatus::Archived : OrganizationStatus::Active, $createdAt, $archivedAt, $emailDomain);
    }

    public function isActive(): bool
    {
        return OrganizationStatus::Active === $this->status;
    }

    public function rename(OrganizationName $name, ?Slug $slug = null): void
    {
        $this->name = $name;
        $this->slug = $slug ?? $this->slug;
    }

    public function archive(DateTimeImmutable $now): void
    {
        $this->status = OrganizationStatus::Archived;
        $this->archivedAt = Moment::of($now);
    }

    public function restore(): void
    {
        $this->status = OrganizationStatus::Active;
        $this->archivedAt = null;
    }

    public function useDomain(?EmailDomain $domain): void
    {
        $this->emailDomain = $domain;
    }
}
