<?php

declare(strict_types=1);

namespace Phil\BackupBundle\Infrastructure\Process;

use Closure;
use Override;
use Symfony\Component\Process\Process;

/**
 * A process that calls a watcher each time `wait()` checks its timeout: at least every 0.2 s while it runs, between
 * reads of its output and writes of its input, so the watcher sees a slow tool without slowing its streams down.
 *
 * @internal used by `ProcessCommandRunner`
 */
final class WatchedProcess extends Process
{
    /**
     * @var (Closure(self): void)|null
     */
    private ?Closure $watcher = null;

    /**
     * @param Closure(self): void $watcher
     */
    public function watch(Closure $watcher): void
    {
        $this->watcher = $watcher;
    }

    #[Override]
    public function checkTimeout(): void
    {
        parent::checkTimeout();

        if ($this->watcher instanceof Closure) {
            ($this->watcher)($this);
        }
    }
}
