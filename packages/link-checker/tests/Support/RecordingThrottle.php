<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Tests\Support;

use Override;
use Phil\LinkChecker\Contract\HostThrottle;
use Symfony\Contracts\Service\ResetInterface;

/**
 * A throttle that never waits, and remembers the hosts it was asked for and its resets.
 */
final class RecordingThrottle implements HostThrottle, ResetInterface
{
    /** @var list<string> */
    public array $hosts = [];

    public int $resets = 0;

    #[Override]
    public function wait(string $host): void
    {
        $this->hosts[] = $host;
    }

    #[Override]
    public function reset(): void
    {
        ++$this->resets;
    }
}
