<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Fixture;

use Override;
use Phil\CoursesBundle\Application\Contract\Cmi5Endpoints;
use Phil\CoursesBundle\Domain\ValueObject\LaunchSessionId;

/**
 * The cmi5 endpoints of a site at https://learn.example.org.
 */
final readonly class FixedEndpoints implements Cmi5Endpoints
{
    #[Override]
    public function endpoint(): string
    {
        return 'https://learn.example.org/cmi5/xapi/';
    }

    #[Override]
    public function fetch(LaunchSessionId $session): string
    {
        return 'https://learn.example.org/cmi5/fetch/'.$session->value;
    }
}
