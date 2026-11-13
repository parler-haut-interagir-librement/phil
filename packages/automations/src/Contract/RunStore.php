<?php

declare(strict_types=1);

namespace Phil\Automations\Contract;

use DateTimeImmutable;
use Phil\Automations\Run\AutomationRun;
use Phil\Automations\Run\RunId;

/**
 * Where runs are kept. Two promises make the engine safe with several workers: a run is created once for one event,
 * and a run is saved only over the revision it was read at.
 */
interface RunStore
{
    /**
     * Keeps a new run unless one of the same id is there.
     *
     * @return bool `false` when the run was already there: the event was seen before
     */
    public function create(AutomationRun $run): bool;

    public function get(RunId $id): ?AutomationRun;

    /**
     * Saves a run over the revision it was read at, with its revision raised by one.
     *
     * @return bool `false` when another worker saved it in between: the caller stops and leaves the run to it
     */
    public function save(AutomationRun $run): bool;

    /**
     * The runs waiting or running whose time to go on has come: a delay over, a retry due, a worker that stopped
     * halfway. A limit under 1 counts as 1.
     *
     * @return list<RunId>
     */
    public function due(DateTimeImmutable $now, int $limit): array;

    /**
     * Forgets the finished runs last changed before an instant.
     *
     * @return int how many were forgotten
     */
    public function purge(DateTimeImmutable $before): int;
}
