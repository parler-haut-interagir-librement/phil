<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Application\Contract;

/**
 * Adds paths of the site to the list the service worker caches when it installs: the pages of a screen, the programme
 * of an event and its data. A service implementing it is tagged `phil_pwa.precache_provider` by autoconfiguration.
 * It answers paths from the root — `/screens/hall`, `/schedule.json` —, the same on every call while the deployment
 * does not change, since the list is the version of the caches.
 */
interface PrecacheProvider
{
    /**
     * @return iterable<string>
     */
    public function urls(): iterable;
}
