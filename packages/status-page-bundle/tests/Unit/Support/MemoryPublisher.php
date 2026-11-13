<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Tests\Unit\Support;

use Override;
use Phil\StatusPageBundle\Application\Contract\SnapshotPublisher;
use Phil\StatusPageBundle\Application\Snapshot\Snapshot;
use RuntimeException;

final class MemoryPublisher implements SnapshotPublisher
{
    /** @var list<Snapshot> */
    public array $published = [];

    public function __construct(
        private readonly bool $fails = false,
    ) {
    }

    #[Override]
    public function publish(Snapshot $snapshot): void
    {
        if ($this->fails) {
            throw new RuntimeException('secret-host.internal refused the connection');
        }

        $this->published[] = $snapshot;
    }
}
