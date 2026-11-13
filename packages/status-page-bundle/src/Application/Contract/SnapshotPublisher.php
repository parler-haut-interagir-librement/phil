<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Application\Contract;

use Phil\StatusPageBundle\Application\Snapshot\Snapshot;

/**
 * Puts the files of a snapshot where visitors read them when the site itself is down: a directory another server
 * serves, a Flysystem storage, or a service of the application's own. A publisher writes every file or throws.
 */
interface SnapshotPublisher
{
    public function publish(Snapshot $snapshot): void;
}
