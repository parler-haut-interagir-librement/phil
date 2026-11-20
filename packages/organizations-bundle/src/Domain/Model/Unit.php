<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Model;

use DateTimeImmutable;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\Status\OrganizationStatus;
use Phil\OrganizationsBundle\Domain\ValueObject\Moment;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationName;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitId;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitPath;

/**
 * A unit of an organization — a department, a service, a branch —, in a tree: a root, or the child of another unit of
 * the same organization. Its path names its ancestors; an archived unit is read only, its members kept.
 */
final class Unit
{
    /** The units an organization holds at most, archived ones included. */
    public const int MAX_PER_ORGANIZATION = 10_000;

    private function __construct(
        public readonly UnitId $id,
        public readonly OrganizationId $organizationId,
        public private(set) OrganizationName $name,
        public private(set) UnitPath $path,
        public private(set) OrganizationStatus $status,
        public readonly DateTimeImmutable $createdAt,
        public private(set) ?DateTimeImmutable $archivedAt,
    ) {
    }

    /**
     * @param Unit|null $parent the unit it is a child of, of the same organization; a root when null
     *
     * @throws InvalidOrganization when the parent is of another organization, or the unit would be too deep
     */
    public static function create(UnitId $id, OrganizationId $organizationId, OrganizationName $name, ?self $parent, DateTimeImmutable $now): self
    {
        return new self($id, $organizationId, $name, self::pathUnder($id, $organizationId, $parent), OrganizationStatus::Active, Moment::of($now), null);
    }

    /**
     * A unit read back from a store.
     *
     * @throws InvalidOrganization when the path does not end with the unit
     */
    public static function restored(UnitId $id, OrganizationId $organizationId, OrganizationName $name, UnitPath $path, DateTimeImmutable $createdAt, ?DateTimeImmutable $archivedAt): self
    {
        if (!$path->unitId()->equals($id)) {
            throw InvalidOrganization::because(\sprintf('The path of the unit "%s" ends with another unit.', $id->value));
        }

        return new self($id, $organizationId, $name, $path, $archivedAt instanceof DateTimeImmutable ? OrganizationStatus::Archived : OrganizationStatus::Active, $createdAt, $archivedAt);
    }

    public function parentId(): ?UnitId
    {
        return $this->path->parentId();
    }

    public function isActive(): bool
    {
        return OrganizationStatus::Active === $this->status;
    }

    public function rename(OrganizationName $name): void
    {
        $this->name = $name;
    }

    /**
     * Moves the unit under another unit, or to the root.
     *
     * @return UnitPath the path it had, from which a store moves its descendants
     *
     * @throws InvalidOrganization when the parent is the unit, one of its descendants or of another organization, or
     *                             the unit would be too deep
     */
    public function moveUnder(?self $parent): UnitPath
    {
        if ($parent instanceof self && $parent->path->isWithin($this->path)) {
            throw InvalidOrganization::because(\sprintf('The unit "%s" cannot move under itself or one of its descendants.', $this->id->value));
        }

        $from = $this->path;
        $this->path = self::pathUnder($this->id, $this->organizationId, $parent);

        return $from;
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

    private static function pathUnder(UnitId $id, OrganizationId $organizationId, ?self $parent): UnitPath
    {
        if (!$parent instanceof self) {
            return UnitPath::root($id);
        }

        if (!$parent->organizationId->equals($organizationId)) {
            throw InvalidOrganization::because(\sprintf('The unit "%s" is of another organization.', $parent->id->value));
        }

        return $parent->path->child($id);
    }
}
