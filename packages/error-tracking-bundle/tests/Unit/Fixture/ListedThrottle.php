<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Fixture;

use Override;
use Phil\ErrorTracking\Contract\CaptureThrottle;

/**
 * A throttle that refuses the keys it lists.
 */
final readonly class ListedThrottle implements CaptureThrottle
{
    /**
     * @param list<string> $refused
     */
    public function __construct(
        private array $refused,
    ) {
    }

    #[Override]
    public function allow(string $key): bool
    {
        return !\in_array($key, $this->refused, true);
    }
}
