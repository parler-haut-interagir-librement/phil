<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Fixture;

use Override;
use Phil\NewsletterBundle\Application\Contract\SendThrottle;
use Phil\NewsletterBundle\Application\Sending\SenderProfile;

/**
 * A throttle that gives the waits it is told, then none, and keeps the domains it was asked for.
 */
final class ScriptedThrottle implements SendThrottle
{
    /** @var list<string> */
    public array $domains = [];

    /**
     * @param list<int> $waits
     */
    public function __construct(
        public array $waits = [],
    ) {
    }

    #[Override]
    public function wait(SenderProfile $profile, string $domain): int
    {
        $this->domains[] = $profile->name.'|'.$domain;

        return array_shift($this->waits) ?? 0;
    }
}
