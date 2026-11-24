<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Contract;

use Phil\MailAuthCheck\Exception\DnsLookupFailed;

/**
 * Reads the TXT records of a name. The checks ask it for SPF records, DKIM keys under `_domainkey` and DMARC records
 * under `_dmarc`; a resolver that follows CNAME records answers for a DKIM key delegated to a provider.
 */
interface TxtResolver
{
    /**
     * @param string $asciiName a name in lowercase ASCII, without a final dot
     *
     * @return list<string> one text per record, its character strings joined without separator; empty when the name
     *                      has no TXT record or does not exist
     *
     * @throws DnsLookupFailed when the resolver could not answer
     */
    public function txt(string $asciiName): array;
}
