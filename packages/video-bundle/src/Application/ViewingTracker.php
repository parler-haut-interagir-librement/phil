<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Application;

use Phil\VideoBundle\Application\Contract\ViewingConsent;
use Phil\VideoBundle\Application\Contract\ViewingStore;
use Phil\VideoBundle\Application\Event\VideoProgressed;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Counts a viewing only when tracking is on and the viewer agreed: its parts added to the heat map, and
 * `VideoProgressed` dispatched. Otherwise the viewing is dropped and nothing is said.
 */
final readonly class ViewingTracker
{
    public function __construct(
        private ViewingConsent $consent,
        private ViewingStore $store,
        private EventDispatcherInterface $dispatcher,
        private bool $enabled = true,
    ) {
    }

    /**
     * Whether a viewing of the current viewer is counted: tracking is on, and they agreed.
     */
    public function isEnabled(): bool
    {
        return $this->enabled && $this->consent->granted();
    }

    /**
     * @return bool whether the viewing was counted
     */
    public function record(ViewingProgress $progress): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $this->store->add($progress);
        $this->dispatcher->dispatch(new VideoProgressed($progress));

        return true;
    }
}
