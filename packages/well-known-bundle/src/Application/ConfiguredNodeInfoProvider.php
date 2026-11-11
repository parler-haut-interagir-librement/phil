<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Application;

use Phil\WellKnownBundle\Application\Contract\NodeInfoProvider;
use Phil\WellKnownBundle\Domain\ValueObject\NodeInfoSoftware;
use Phil\WellKnownBundle\Domain\ValueObject\NodeInfoUsage;

/**
 * The NodeInfo of the `nodeinfo` configuration, when the application names no provider: its software, whether it takes
 * new users, its metadata, and no usage count.
 */
final readonly class ConfiguredNodeInfoProvider implements NodeInfoProvider
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private NodeInfoSoftware $software,
        private bool $openRegistrations,
        private array $metadata,
    ) {
    }

    public function software(): NodeInfoSoftware
    {
        return $this->software;
    }

    public function usage(): NodeInfoUsage
    {
        return new NodeInfoUsage();
    }

    public function openRegistrations(): bool
    {
        return $this->openRegistrations;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }
}
