<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Dmarc;

/**
 * The DMARC record that applies to a domain, as `DmarcTreeWalk` found it.
 */
final readonly class DmarcDiscovery
{
    /**
     * @param ?string               $foundAt      the domain whose `_dmarc` holds the record: the domain itself or a parent
     * @param bool                  $lookupFailed a lookup of the walk failed, which ended it
     * @param array<string, string> $ignored      why the records of each domain on the way were skipped, by domain
     */
    public function __construct(
        public string $domain,
        public ?DmarcRecord $record = null,
        public ?string $foundAt = null,
        public bool $lookupFailed = false,
        public array $ignored = [],
    ) {
    }

    /**
     * Whether the record was found on a parent domain rather than on the domain itself.
     */
    public function isInherited(): bool
    {
        return null !== $this->foundAt && $this->foundAt !== $this->domain;
    }
}
