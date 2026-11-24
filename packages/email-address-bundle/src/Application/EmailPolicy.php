<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Application;

use Phil\EmailAddress\Validator\DeliverableEmailAddress;

/**
 * What a form accepts of an address: whether a mail server is looked for, whether disposable domains pass, whether the
 * top-level domain must be a delegated one, whether the part before the @ may hold international characters, and the
 * domains refused or the only ones accepted.
 *
 * A policy may leave a choice open — `null` — to the one it is laid over: the bundle's, under a site's, under a
 * list's. Denied domains add up; the only domains accepted are the most specific policy's that names some.
 */
final readonly class EmailPolicy
{
    /**
     * @param list<string>      $deniedDomains  domains refused, with their subdomains, added to those of the policy under it
     * @param list<string>|null $allowedDomains the only domains accepted, with their subdomains; `null` keeps those of the
     *                                          policy under it, an empty list accepts every domain
     */
    public function __construct(
        public ?bool $checkMailServer = null,
        public ?bool $allowDisposable = null,
        public ?bool $knownTopLevelDomain = null,
        public ?bool $internationalLocalPart = null,
        public array $deniedDomains = [],
        public ?array $allowedDomains = null,
    ) {
    }

    /**
     * This policy laid over another: its choices where it makes them, the other's elsewhere.
     */
    public function over(self $base): self
    {
        return new self(
            $this->checkMailServer ?? $base->checkMailServer,
            $this->allowDisposable ?? $base->allowDisposable,
            $this->knownTopLevelDomain ?? $base->knownTopLevelDomain,
            $this->internationalLocalPart ?? $base->internationalLocalPart,
            array_values(array_unique([...$base->deniedDomains, ...$this->deniedDomains])),
            $this->allowedDomains ?? $base->allowedDomains,
        );
    }

    /**
     * The library's constraint for this policy, a choice left open taking the constraint's default: no mail server
     * looked for, no disposable domain, a delegated top-level domain, an ASCII part before the @.
     *
     * @param list<string>|null $groups
     */
    public function constraint(?array $groups = null): DeliverableEmailAddress
    {
        return new DeliverableEmailAddress(
            internationalLocalPart: $this->internationalLocalPart ?? false,
            allowDisposable: $this->allowDisposable ?? false,
            knownTopLevelDomain: $this->knownTopLevelDomain ?? true,
            checkMailServer: $this->checkMailServer ?? false,
            deniedDomains: $this->deniedDomains,
            allowedDomains: $this->allowedDomains ?? [],
            groups: $groups,
        );
    }
}
