<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Fixture;

use Override;
use Phil\BrowserSecurityBundle\Application\Contract\ClientAddressAnonymizer;
use Phil\BrowserSecurityBundle\Application\Contract\ReportDeduplicator;
use Phil\BrowserSecurityBundle\Application\Contract\ReportThrottle;

/**
 * A throttle, a deduplicator and an anonymizer in one: the clients refused, the fingerprints seen, the addresses
 * asked, all kept for the assertions.
 */
final class ListedThrottle implements ReportThrottle, ReportDeduplicator, ClientAddressAnonymizer
{
    /** @var list<string> */
    public array $clients = [];

    /** @var list<string> */
    public array $fingerprints = [];

    /**
     * @param list<string> $refused
     * @param list<string> $known
     */
    public function __construct(
        private readonly array $refused = [],
        private readonly array $known = [],
    ) {
    }

    #[Override]
    public function allow(string $client): bool
    {
        $this->clients[] = $client;

        return !\in_array($client, $this->refused, true);
    }

    #[Override]
    public function isNew(string $fingerprint): bool
    {
        $this->fingerprints[] = $fingerprint;

        return !\in_array($fingerprint, $this->known, true);
    }

    #[Override]
    public function anonymize(string $address): ?string
    {
        return '' === $address ? null : 'anon:'.$address;
    }
}
