<?php

declare(strict_types=1);

namespace Phil\ErrorTracking\Tests\Unit\Fixture;

use Override;
use Phil\ErrorTracking\Contract\GroupAlerter;
use Phil\ErrorTracking\ErrorGroup;
use Phil\ErrorTracking\Occurrence;

final class RecordingAlerter implements GroupAlerter
{
    /**
     * @var list<array{ErrorGroup, Occurrence}>
     */
    public array $alerts = [];

    #[Override]
    public function alert(ErrorGroup $group, Occurrence $occurrence): void
    {
        $this->alerts[] = [$group, $occurrence];
    }
}
