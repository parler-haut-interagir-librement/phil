<?php

declare(strict_types=1);

namespace Phil\ErrorTracking\Tests\Unit\Fixture;

use Override;
use Phil\ErrorTracking\Contract\ErrorStore;
use Phil\ErrorTracking\ErrorEvent;
use Phil\ErrorTracking\ErrorGroup;

final class MemoryStore implements ErrorStore
{
    /**
     * @var array<string, ErrorGroup>
     */
    public array $groups = [];

    /**
     * @var array<string, list<ErrorEvent>>
     */
    public array $events = [];

    #[Override]
    public function find(string $fingerprint): ?ErrorGroup
    {
        return $this->groups[$fingerprint] ?? null;
    }

    #[Override]
    public function save(ErrorGroup $group, ?ErrorEvent $event = null): void
    {
        $this->groups[$group->fingerprint] = $group;

        if ($event instanceof ErrorEvent) {
            $this->events[$group->fingerprint][] = $event;
        }
    }
}
