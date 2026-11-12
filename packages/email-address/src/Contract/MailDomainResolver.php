<?php

declare(strict_types=1);

namespace Phil\EmailAddress\Contract;

use Phil\EmailAddress\MailDomain;

/**
 * Looks up whether a domain receives mail. The validator asks it once per address; an adapter of the application may
 * cache answers, use another resolver or answer from a list.
 */
interface MailDomainResolver
{
    /**
     * @param string $asciiDomain a domain name in its ASCII form, without a final dot
     */
    public function resolve(string $asciiDomain): MailDomain;
}
