<?php

declare(strict_types=1);

namespace Phil\LegalNotices\Tests\Unit\Fixture;

use Override;
use Phil\LegalNotices\Contract\RetentionDurations;
use Phil\LegalNotices\RetentionDuration;

final readonly class ListedRetention implements RetentionDurations
{
    /**
     * @param list<RetentionDuration> $durations
     */
    public function __construct(
        private array $durations,
    ) {
    }

    #[Override]
    public function durations(): array
    {
        return $this->durations;
    }
}
