<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\ORM\EntityManagerInterface;
use Phil\DoctrineBehaviours\Contract\PersonalFields;
use Phil\DoctrineBehaviours\Contract\Versionable;
use Phil\DoctrineBehaviours\Contract\VersionCipher;
use Phil\DoctrineBehaviours\Contract\VersionRestoreGuard;
use Phil\DoctrineBehaviours\Exception\InvalidVersionedState;
use Phil\DoctrineBehaviours\Exception\UnknownVersion;
use Phil\DoctrineBehaviours\Exception\VersionErased;
use Phil\DoctrineBehaviours\Exception\VersionRestoreDenied;
use Phil\DoctrineBehaviours\Version\RecordVersion;
use Psr\Clock\ClockInterface;

/**
 * The versions of a `Versionable` record: which were frozen, freezing the one being worked on, and going back to a
 * past one. Which event freezes a version is the application's decision; the numbering is not.
 *
 * Going back rewrites no history: the version being worked on is frozen first, so nothing is lost, and the state of
 * the past version becomes the next number. It takes the right `VersionRestoreGuard` checks.
 *
 * The personal fields `PersonalFields` names follow the `PersonalFieldPolicy`: left out of the version by default,
 * sealed by a `VersionCipher`, or kept. Going back keeps the values the record holds for a field the version left
 * out, opens a sealed one, and is refused for a version an erasure redacted, or older than one.
 */
final readonly class Versions
{
    public const int DEFAULT_LIST_LIMIT = 50;

    /**
     * The key of a sealed value in the state of a version.
     */
    public const string SEALED = '@sealed';

    /**
     * @throws InvalidVersionedState when the policy seals and no cipher is given
     */
    public function __construct(
        private EntityManagerInterface $manager,
        private ClockInterface $clock,
        private VersionRestoreGuard $guard,
        private PersonalFields $personalFields = new NoPersonalFields(),
        private PersonalFieldPolicy $policy = PersonalFieldPolicy::Exclude,
        private ?VersionCipher $cipher = null,
    ) {
        if (PersonalFieldPolicy::Encrypt === $policy && !$cipher instanceof VersionCipher) {
            throw InvalidVersionedState::noCipher();
        }
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
            $version = new RecordVersion($subject['subjectTable'], $subject['subjectId'], $entity->versionNumber(), $this->protect($entity, $subject, $state), $this->clock->now());
            $this->manager->persist($version);
            $entity->markVersionFrozen();

            return $version;
        });
    }

    /**
     * Goes back to a past version: its state becomes the next number, and flushes.
     *
     * @throws UnknownVersion        when the record has no such frozen version
     * @throws VersionErased         when an erasure redacted the version, or a later one of the record
     * @throws VersionRestoreDenied  when the guard refuses it, or an exception of the guard's own
     * @throws InvalidVersionedState when the version holds a sealed field and no cipher is given
     */
    public function restore(Versionable $entity, int $number): void
    {
        $this->guard->check($entity, $number);
        $version = $this->get($entity, $number) ?? throw UnknownVersion::of($entity::class, $entity->versionSubjectId(), $number);

        if ($version->isRedacted()) {
            throw VersionErased::redacted($entity::class, $entity->versionSubjectId(), $number);
        }

        if ($this->isErasedAfter($entity, $number)) {
            throw VersionErased::erasedRecord($entity::class, $entity->versionSubjectId(), $number);
        }

        $state = $this->reveal($entity, $version);

        $this->manager->wrapInTransaction(function () use ($entity, $state): void {
            $this->freeze($entity);
            $entity->applyVersionedState($state);
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

    /**
     * The state to freeze, its personal fields left out, sealed or kept.
     *
     * @param array{subjectTable: string, subjectId: string} $subject
     * @param array<string, mixed>                           $state
     *
     * @return array<string, mixed>
     */
    private function protect(Versionable $entity, array $subject, array $state): array
    {
        if (PersonalFieldPolicy::Keep === $this->policy) {
            return $state;
        }

        foreach ($this->personalFields->of($entity::class) as $field) {
            if (!\array_key_exists($field, $state)) {
                continue;
            }

            if ($this->cipher instanceof VersionCipher && PersonalFieldPolicy::Encrypt === $this->policy) {
                $state[$field] = [self::SEALED => $this->cipher->seal(json_encode($state[$field], \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION), $this->context($subject, $entity->versionNumber(), $field))];
            } else {
                unset($state[$field]);
            }
        }

        return $state;
    }

    /**
     * The state to go back to: a sealed field opened, a personal field the version left out taken from the record.
     *
     * @return array<string, mixed>
     */
    private function reveal(Versionable $entity, RecordVersion $version): array
    {
        $state = $version->state();
        $current = null;

        foreach ($this->personalFields->of($entity::class) as $field) {
            if (!\array_key_exists($field, $state)) {
                $current ??= $entity->versionedState();

                if (\array_key_exists($field, $current)) {
                    $state[$field] = $current[$field];
                }

                continue;
            }

            $sealed = \is_array($state[$field]) ? ($state[$field][self::SEALED] ?? null) : null;

            if (!\is_string($sealed)) {
                continue;
            }

            if (!$this->cipher instanceof VersionCipher) {
                throw InvalidVersionedState::sealed($entity::class, $field);
            }

            $state[$field] = json_decode($this->cipher->open($sealed, $this->context(['subjectTable' => $version->subjectTable(), 'subjectId' => $version->subjectId()], $version->number(), $field)), true, 512, \JSON_THROW_ON_ERROR);
        }

        return $state;
    }

    private function isErasedAfter(Versionable $entity, int $number): bool
    {
        $subject = $this->subject($entity);
        $later = $this->manager->createQueryBuilder()
            ->select('COUNT(v.id)')
            ->from(RecordVersion::class, 'v')
            ->where('v.subjectTable = :table AND v.subjectId = :id AND v.number > :number AND v.redactedAt IS NOT NULL')
            ->setParameter('table', $subject['subjectTable'])
            ->setParameter('id', $subject['subjectId'])
            ->setParameter('number', $number)
            ->getQuery()
            ->getSingleScalarResult();

        return is_numeric($later) && $later > 0;
    }

    /**
     * @param array{subjectTable: string, subjectId: string} $subject
     */
    private function context(array $subject, int $number, string $field): string
    {
        return \sprintf('phil_record_version|%s|%s|%d|%s', $subject['subjectTable'], $subject['subjectId'], $number, $field);
    }
}
