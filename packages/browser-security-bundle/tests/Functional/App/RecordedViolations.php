<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Functional\App;

use Phil\BrowserSecurityBundle\Application\Event\ViolationReported;

/**
 * The violations the application was told of, kept for the assertions.
 */
final class RecordedViolations
{
    /** @var list<ViolationReported> */
    public array $events = [];

    public function __invoke(ViolationReported $event): void
    {
        $this->events[] = $event;
    }
}
