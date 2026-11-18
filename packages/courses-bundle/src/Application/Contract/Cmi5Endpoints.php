<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Contract;

use Phil\CoursesBundle\Domain\ValueObject\LaunchSessionId;

/**
 * The absolute URLs an assignable unit is launched with: the xAPI endpoint, ending with a slash, and the fetch URL of
 * a session.
 */
interface Cmi5Endpoints
{
    public function endpoint(): string;

    public function fetch(LaunchSessionId $session): string;
}
