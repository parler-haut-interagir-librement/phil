<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Tests\Fixture;

use Override;
use Phil\IncidentMode\Activation;
use Phil\IncidentMode\Contract\LiftScheduler;

/**
 * Keeps every activation it was asked to schedule the lifting of, in order.
 */
final class RecordingScheduler implements LiftScheduler
{
    /** @var list<Activation> */
    public array $scheduled = [];

    #[Override]
    public function schedule(Activation $activation): void
    {
        $this->scheduled[] = $activation;
    }
}
