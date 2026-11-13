<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Automations\Contract\RunStore;
use Phil\Automations\Run\AutomationRun;
use Phil\Automations\Run\InMemoryRunStore;
use Phil\Automations\Run\RunId;

/**
 * A run store that keeps the limits it is asked for and counts its saves.
 */
final class WatchedRunStore implements RunStore
{
    /** @var list<int> */
    public array $limits = [];

    public int $saves = 0;

    public function __construct(
        private readonly InMemoryRunStore $inner = new InMemoryRunStore(),
    ) {
    }

    #[Override]
    public function create(AutomationRun $run): bool
    {
        return $this->inner->create($run);
    }

    #[Override]
    public function get(RunId $id): ?AutomationRun
    {
        return $this->inner->get($id);
    }

    #[Override]
    public function save(AutomationRun $run): bool
    {
        ++$this->saves;

        return $this->inner->save($run);
    }

    #[Override]
    public function due(DateTimeImmutable $now, int $limit): array
    {
        $this->limits[] = $limit;

        return $this->inner->due($now, $limit);
    }

    #[Override]
    public function purge(DateTimeImmutable $before): int
    {
        return $this->inner->purge($before);
    }
}
