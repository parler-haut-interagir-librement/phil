<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Dmarc;

use Phil\MailAuthCheck\Alignment;
use Phil\MailAuthCheck\DmarcPolicy;

/**
 * A DMARC record read by `DmarcParser` (RFC 9989).
 */
final readonly class DmarcRecord
{
    /**
     * @param ?DmarcPolicy $policy            `p`; null when the record has none, which receivers read as `none`
     * @param ?DmarcPolicy $subdomainPolicy   `sp`, for the subdomains of the domain that publishes the record
     * @param ?DmarcPolicy $nonExistentPolicy `np`, for its subdomains that do not exist
     * @param bool         $testing           `t=y`: receivers apply the policy one step weaker
     * @param ?bool        $publicSuffix      `psd=y` (a public suffix), `psd=n` (an organisational domain), null otherwise
     * @param list<string> $aggregateReports  the addresses of `rua`, as written
     */
    public function __construct(
        public ?DmarcPolicy $policy,
        public ?DmarcPolicy $subdomainPolicy = null,
        public ?DmarcPolicy $nonExistentPolicy = null,
        public Alignment $spfAlignment = Alignment::Relaxed,
        public Alignment $dkimAlignment = Alignment::Relaxed,
        public bool $testing = false,
        public ?bool $publicSuffix = null,
        public array $aggregateReports = [],
    ) {
    }

    /**
     * The policy for mail from the domain that publishes the record, or from one of its existing subdomains.
     */
    public function policyFor(bool $subdomain): DmarcPolicy
    {
        return ($subdomain ? $this->subdomainPolicy : null) ?? $this->policy ?? DmarcPolicy::None;
    }
}
