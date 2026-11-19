<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\ErrorTracking\Contract\ErrorStore;
use Phil\ErrorTracking\ErrorEvent;
use Phil\ErrorTracking\ErrorGroup;
use Phil\ErrorTracking\GroupStatus;
use Phil\ErrorTrackingBundle\Application\Contract\ErrorGroupReader;
use RuntimeException;

final class MemoryStore implements ErrorStore, ErrorGroupReader
{
    /**
     * @var array<string, ErrorGroup>
     */
    public array $groups = [];

    /**
     * @var array<string, list<ErrorEvent>>
     */
    public array $events = [];

    public ?DateTimeImmutable $purgedBefore = null;

    public bool $failing = false;

    #[Override]
    public function find(string $fingerprint): ?ErrorGroup
    {
        return $this->groups[$fingerprint] ?? null;
    }

    #[Override]
    public function save(ErrorGroup $group, ?ErrorEvent $event = null): void
    {
        if ($this->failing) {
            throw new RuntimeException('The database is gone.');
        }

        $this->groups[$group->fingerprint] = $group;

        if ($event instanceof ErrorEvent) {
            $this->events[$group->fingerprint][] = $event;
        }
    }

    /**
     * The only event kept, for the tests that capture one.
     */
    public function onlyEvent(): ErrorEvent
    {
        $events = array_merge(...array_values($this->events));

        if (1 !== \count($events)) {
            throw new RuntimeException(\sprintf('%d events kept.', \count($events)));
        }

        return $events[0];
    }

    #[Override]
    public function groups(?GroupStatus $status, int $limit, int $offset = 0): array
    {
        $groups = array_values(array_filter($this->groups, static fn (ErrorGroup $group): bool => !$status instanceof GroupStatus || $group->status === $status));

        return \array_slice($groups, $offset, $limit);
    }

    #[Override]
    public function countGroups(?GroupStatus $status): int
    {
        return \count(array_filter($this->groups, static fn (ErrorGroup $group): bool => !$status instanceof GroupStatus || $group->status === $status));
    }

    #[Override]
    public function events(string $fingerprint, int $limit): array
    {
        return \array_slice(array_reverse($this->events[$fingerprint] ?? []), 0, $limit);
    }

    #[Override]
    public function purge(DateTimeImmutable $before): int
    {
        $this->purgedBefore = $before;

        return 3;
    }
}
