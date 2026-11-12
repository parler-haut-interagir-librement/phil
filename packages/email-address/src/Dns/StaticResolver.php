<?php

declare(strict_types=1);

namespace Phil\EmailAddress\Dns;

use Override;
use Phil\EmailAddress\Contract\MailDomainResolver;
use Phil\EmailAddress\MailDomain;

/**
 * Answers from a map of domains, and with one answer for the others: for tests, for domains the application knows,
 * or to turn the check off with `MailDomain::Unknown`.
 */
final readonly class StaticResolver implements MailDomainResolver
{
    /**
     * @param array<string, MailDomain> $answers by ASCII domain, in lowercase
     */
    public function __construct(
        private array $answers = [],
        private MailDomain $otherwise = MailDomain::Unknown,
    ) {
    }

    #[Override]
    public function resolve(string $asciiDomain): MailDomain
    {
        return $this->answers[$asciiDomain] ?? $this->otherwise;
    }
}
