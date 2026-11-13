<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Tests\Functional\App;

use Override;
use Phil\PwaBundle\Application\Contract\PrecacheProvider;

/**
 * A provider of the application, tagged by autoconfiguration: the data of a programme.
 */
final readonly class ScheduleData implements PrecacheProvider
{
    #[Override]
    public function urls(): iterable
    {
        yield '/schedule.json';
    }
}
