<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Phil\DoctrineBehaviours\Contract\Sluggable;
use Phil\DoctrineBehaviours\Contract\Translation;
use Phil\DoctrineBehaviours\Exception\InvalidScope;
use Phil\DoctrineBehaviours\Exception\SlugUnavailable;
use Phil\Slug\Slug;
use Phil\Slug\Sluggers;

/**
 * Gives `Sluggable` entities their slug as they are flushed, by the sluggers of `phil/slug`, and keeps it unique in
 * the table, or within the scope the entity names: `about`, then `about-2`, `about-3`, up to a bound. One query per
 * slug settles it, on the table itself, so that rows the SQL filters hide still count: their index entry is there.
 * Entities of the same flush count too. The slug of a `Translation` is unique within a scope that names its locale, so
 * that `fr/about` and `en/about` can coexist. An entity that moves to another scope keeps its slug when it is free there,
 * and is numbered when it is not. An insert that races another still meets the unique index, and the application
 * retries it.
 */
final readonly class SlugListener
{
    public const int MAX_ATTEMPTS = 100;

    public function __construct(
        private Sluggers $sluggers,
    ) {
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $manager = $event->getObjectManager();
        $work = $manager->getUnitOfWork();
        $reserved = [];

        foreach ([...$work->getScheduledEntityInsertions(), ...$work->getScheduledEntityUpdates()] as $entity) {
            if (!$entity instanceof Sluggable) {
                continue;
            }

            $changes = $work->getEntityChangeSet($entity);
            $change = $changes[Sluggable::SLUG_FIELD] ?? null;
            $current = $entity->slug();
            $fields = $entity->slugScope();

            if ($entity instanceof Translation && !\in_array(Translation::LOCALE_FIELD, $fields, true)) {
                throw InvalidScope::translationWithoutLocale($entity::class);
            }

            $moved = [] !== array_intersect($fields, array_keys($changes)) && !$work->isScheduledForInsert($entity);

            if (null !== $current && null === $change && !$moved) {
                continue;
            }

            $metadata = $manager->getClassMetadata($entity::class);
            $scope = Scope::of($manager, $metadata, $entity, $fields);
            $table = Scope::tableOf($manager, $metadata, Sluggable::SLUG_FIELD);
            $wanted = $current ?? $this->sluggers->slug($entity->slugSource(), $entity->sluggerName())->value;
            /** @var ?string $previous */
            $previous = $moved ? null : ($change[0] ?? null);
            $free = $this->firstFree($manager, $metadata, $table, $scope, $wanted, $previous, $reserved[$table][$scope->key] ?? []);
            $reserved[$table][$scope->key][$free] = true;

            if ($free !== $current) {
                $entity->assignSlug(Slug::fromString($free));
                $work->recomputeSingleEntityChangeSet($metadata, $entity);
            }
        }
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param array<string, true>   $reserved the slugs other entities of this flush took in this scope
     */
    private function firstFree(EntityManagerInterface $manager, ClassMetadata $metadata, string $table, Scope $scope, string $wanted, ?string $previous, array $reserved): string
    {
        $length = min($metadata->getFieldMapping(Sluggable::SLUG_FIELD)->length ?? Slug::MAX_LENGTH, Slug::MAX_LENGTH);
        $candidates = $this->candidates($wanted, $length);
        $column = $manager->getConfiguration()->getQuoteStrategy()->getColumnName(Sluggable::SLUG_FIELD, $metadata, $manager->getConnection()->getDatabasePlatform());
        /** @var list<string> $rows */
        $rows = $scope->reachesNothing ? [] : $manager->getConnection()->fetchFirstColumn(
            \sprintf('SELECT %1$s FROM %2$s WHERE %1$s IN (?) AND %3$s', $column, $table, $scope->where),
            [$candidates, ...$scope->parameters],
            [ArrayParameterType::STRING, ...$scope->types],
        );
        $taken = array_diff($rows, [$previous]);

        foreach ($candidates as $candidate) {
            if (true !== ($reserved[$candidate] ?? null) && !\in_array($candidate, $taken, true)) {
                return $candidate;
            }
        }

        throw SlugUnavailable::of($wanted, $table, self::MAX_ATTEMPTS);
    }

    /**
     * The slug, then its numbered forms, each cut to fit the column.
     *
     * @return list<string>
     */
    private function candidates(string $wanted, int $length): array
    {
        $candidates = [rtrim(substr($wanted, 0, $length), '-')];

        for ($number = 2; $number <= self::MAX_ATTEMPTS; ++$number) {
            $suffix = '-'.$number;
            $candidates[] = rtrim(substr($wanted, 0, $length - \strlen($suffix)), '-').$suffix;
        }

        return $candidates;
    }
}
