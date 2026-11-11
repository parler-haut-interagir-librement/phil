<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Application\Contract;

use Phil\WellKnownBundle\Domain\ValueObject\NodeInfoSoftware;
use Phil\WellKnownBundle\Domain\ValueObject\NodeInfoUsage;

/**
 * What a NodeInfo document tells that changes with the site: the software, its usage, whether it takes new users and
 * its metadata. Asked on each request of `/nodeinfo/2.1`: an implementation that counts users keeps its counts in a
 * cache of its own.
 */
interface NodeInfoProvider
{
    public function software(): NodeInfoSoftware;

    public function usage(): NodeInfoUsage;

    public function openRegistrations(): bool;

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array;
}
