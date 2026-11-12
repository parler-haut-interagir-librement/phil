<?php

declare(strict_types=1);

namespace Phil\EmailAddress;

use Phil\EmailAddress\Exception\InvalidDomainList;
use Phil\EmailAddress\Internal\DomainSet;

/**
 * The domains an application refuses, and the only domains it accepts when it names some — an internal list kept to
 * the organisation's domain. A domain stands for its subdomains too. Each `with` method returns new rules.
 */
final readonly class DomainRules
{
    private function __construct(
        private DomainSet $denied,
        private DomainSet $allowedOnly,
    ) {
    }

    /**
     * @param iterable<string> $denied      domains refused
     * @param iterable<string> $allowedOnly the only domains accepted, or none to accept every domain not denied
     *
     * @throws InvalidDomainList when one is not a domain name
     */
    public static function of(iterable $denied = [], iterable $allowedOnly = []): self
    {
        return new self(DomainSet::of($denied), DomainSet::of($allowedOnly));
    }

    /**
     * @param iterable<string> $domains
     *
     * @throws InvalidDomainList when one is not a domain name
     */
    public function withDenied(iterable $domains): self
    {
        return new self($this->denied->union(DomainSet::of($domains)), $this->allowedOnly);
    }

    /**
     * @param iterable<string> $domains
     *
     * @throws InvalidDomainList when one is not a domain name
     */
    public function withAllowedOnly(iterable $domains): self
    {
        return new self($this->denied, $this->allowedOnly->union(DomainSet::of($domains)));
    }

    /**
     * `Problem::DeniedDomain`, `Problem::DomainNotAllowed`, or `null` when the rules accept the address. A denied
     * domain is refused even when it is also allowed.
     */
    public function check(EmailAddress $address): ?Problem
    {
        return match (true) {
            $this->denied->contains($address->asciiDomain) => Problem::DeniedDomain,
            !$this->allowedOnly->isEmpty() && !$this->allowedOnly->contains($address->asciiDomain) => Problem::DomainNotAllowed,
            default => null,
        };
    }
}
