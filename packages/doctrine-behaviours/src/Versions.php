<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\ORM\EntityManagerInterface;
use Phil\DoctrineBehaviours\Contract\Versionable;
use Phil\DoctrineBehaviours\Contract\VersionRestoreGuard;
use Phil\DoctrineBehaviours\Exception\InvalidVersionedState;
use Phil\DoctrineBehaviours\Exception\UnknownVersion;
use Phil\DoctrineBehaviours\Exception\VersionRestoreDenied;
use Phil\DoctrineBehaviours\Version\RecordVersion;
use Psr\Clock\ClockInterface;

/**
 * The versions of a `Versionable` record: which were frozen, freezing the one being worked on, and going back to a
 * past one. Which event freezes a version is the application's decision; the numbering is not.
 *
 * Going back rewrites no history: the version being worked on is frozen first, so nothing is lost, and the state of
 * the past version becomes the next number. It takes the right `VersionRestoreGuard` checks.
 */
final readonly class Versions
{
    public const int DEFAULT_LIST_LIMIT = 50;

    public function __construct(
        private EntityManagerInterface $manager,
        private ClockInterface $clock,
        private VersionRestoreGuard $guard,
    ) {
    }

    /**
     * The frozen versions, the latest first.
     *
     * @param positive-int $limit
     *
     * @return list<RecordVersion>
     */
    public function list(Versionable $entity, int $limit = self::DEFAULT_LIST_LIMIT): array
    {
        return $this->manager->getRepository(RecordVersion::class)->findBy($this->subject($entity), ['number' => 'DESC'], $limit);
    }

    public function get(Versionable $entity, int $number): ?RecordVersion
    {
        return $this->manager->getRepository(RecordVersion::class)->findOneBy([...$this->subject($entity), 'number' => $number]);
    }

    public function isFrozen(Versionable $entity, int $number): bool
    {
        return $this->get($entity, $number) instanceof RecordVersion;
    }

    /**
     * Flushes what is pending, then freezes the version being worked on, or gives it back when it is frozen already;
     * `wrapInTransaction()` flushes the snapshot before it commits.
     *
     * @throws InvalidVersionedState when `versionedState()` names something the entity does not map
     */
    public function freeze(Versionable $entity): RecordVersion
    {
        return $this->manager->wrapInTransaction(function () use ($entity): RecordVersion {
            $this->manager->persist($entity);
            $this->manager->flush();

            $frozen = $this->get($entity, $entity->versionNumber());

            if ($frozen instanceof RecordVersion) {
                return $frozen;
            }

            $state = $entity->versionedState();
            $this->assertMapped($entity, $state);
            $subject = $this->subject($entity);
            $version = new RecordVersion($subject['subjectTable'], $subject['subjectId'], $entity->versionNumber(), $state, Utc::now($this->clock));
            $this->manager->persist($version);
            $entity->markVersionFrozen();

            return $version;
        });
    }

    /**
     * Goes back to a past version: its state becomes the next number, and flushes.
     *
     * @throws UnknownVersion       when the record has no such frozen version
     * @throws VersionRestoreDenied when the guard refuses it, or an exception of the guard's own
     */
    public function restore(Versionable $entity, int $number): void
    {
        $this->guard->check($entity, $number);
        $version = $this->get($entity, $number) ?? throw UnknownVersion::of($entity::class, $entity->versionSubjectId(), $number);

        $this->manager->wrapInTransaction(function () use ($entity, $version): void {
            $this->freeze($entity);
            $entity->applyVersionedState($version->state());
        });
    }

    /**
     * @return array{subjectTable: string, subjectId: string}
     */
    private function subject(Versionable $entity): array
    {
        $metadata = $this->manager->getClassMetadata($entity::class);

        return [
            'subjectTable' => $this->manager->getClassMetadata($metadata->rootEntityName)->getTableName(),
            'subjectId' => $entity->versionSubjectId(),
        ];
    }

    /**
     * @param array<string, mixed> $state
     */
    private function assertMapped(Versionable $entity, array $state): void
    {
        $metadata = $this->manager->getClassMetadata($entity::class);

        foreach (array_keys($state) as $field) {
            if (!$metadata->hasField($field) && !$metadata->hasAssociation($field)) {
                throw InvalidVersionedState::unmapped($entity::class, $field);
            }
        }
    }
}
