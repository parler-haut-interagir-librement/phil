<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Tests\Functional\App;

use Phil\CheckinBundle\Application\Event\CheckInRecorded;

/**
 * A listener of the application that keeps the scans the endpoints recorded.
 */
final class RecordedScans
{
    /** @var list<CheckInRecorded> */
    public array $scans = [];

    public function __invoke(CheckInRecorded $scan): void
    {
        $this->scans[] = $scan;
    }
}
