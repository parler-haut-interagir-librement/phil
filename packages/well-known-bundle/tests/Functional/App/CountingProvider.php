<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Tests\Functional\App;

use Phil\WellKnownBundle\Application\Contract\NodeInfoProvider;
use Phil\WellKnownBundle\Domain\ValueObject\NodeInfoSoftware;
use Phil\WellKnownBundle\Domain\ValueObject\NodeInfoUsage;

/**
 * A provider that counts its users.
 */
final readonly class CountingProvider implements NodeInfoProvider
{
    public function software(): NodeInfoSoftware
    {
        return new NodeInfoSoftware('example', '1.2.3', 'https://example.com/code');
    }

    public function usage(): NodeInfoUsage
    {
        return new NodeInfoUsage(totalUsers: 42, activeMonth: 7, localPosts: 1000);
    }

    public function openRegistrations(): bool
    {
        return true;
    }

    public function metadata(): array
    {
        return ['nodeName' => 'Example'];
    }
}
