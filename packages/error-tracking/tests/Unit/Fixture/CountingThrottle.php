<?php

declare(strict_types=1);

namespace Phil\ErrorTracking\Tests\Unit\Fixture;

use Override;
use Phil\ErrorTracking\Contract\CaptureThrottle;

final class CountingThrottle implements CaptureThrottle
{
    /**
     * @var array<string, int>
     */
    public array $asked = [];

    public function __construct(
        private readonly int $limit,
    ) {
    }

    #[Override]
    public function allow(string $key): bool
    {
        $this->asked[$key] = ($this->asked[$key] ?? 0) + 1;

        return $this->asked[$key] <= $this->limit;
    }
}
