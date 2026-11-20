<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\Contract;

use Phil\NewsletterBundle\Domain\Enum\SendRunCounter;
use Phil\NewsletterBundle\Domain\Enum\SendRunStatus;
use Phil\NewsletterBundle\Domain\Model\SendRun;

/**
 * Where the send runs are kept. Their counts are added to, never written over, and their state changes only from the
 * state it was read in, so that workers sending the messages of one run at the same moment lose nothing.
 */
interface SendRunRepository
{
    /**
     * Keeps a new send run unless one of the same id is there.
     *
     * @return bool `false` when it was already there
     */
    public function create(SendRun $run): bool;

    public function get(string $id): ?SendRun;

    /**
     * The send runs of a campaign, by number.
     *
     * @return list<SendRun>
     */
    public function ofCampaign(string $campaignId): array;

    /**
     * Adds to a count of the run.
     */
    public function add(string $runId, SendRunCounter $counter, int $by = 1): void;

    /**
     * Keeps how many messages the run queued, once they are all queued.
     */
    public function queuedAll(SendRun $run): void;

    /**
     * Keeps the state of the run, its pause and its end, if the stored run is still in the state `$from`.
     *
     * @return bool `false` when another worker changed the state first
     */
    public function transition(SendRun $run, SendRunStatus $from): bool;
}
