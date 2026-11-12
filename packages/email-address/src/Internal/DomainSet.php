<?php

declare(strict_types=1);

namespace Phil\EmailAddress\Internal;

use Phil\EmailAddress\Exception\InvalidDomainList;
use Phil\EmailAddress\Exception\InvalidEmailAddress;

/**
 * A set of ASCII domains that also holds their subdomains: `mail.example.com` is in a set that holds `example.com`.
 *
 * @internal
 */
final readonly class DomainSet
{
    /** @var array<string, int> */
    private array $domains;

    /**
     * @param list<string> $domains in their ASCII form
     */
    public function __construct(array $domains)
    {
        $this->domains = array_flip($domains);
    }

    /**
     * @param iterable<string> $domains domain names, in any script and case
     *
     * @throws InvalidDomainList when one is not a domain name
     */
    public static function of(iterable $domains): self
    {
        $set = [];

        foreach ($domains as $domain) {
            try {
                $set[] = DomainName::of($domain)->ascii;
            } catch (InvalidEmailAddress $invalid) {
                throw InvalidDomainList::domain($domain, $invalid->reason);
            }
        }

        return new self($set);
    }

    public function union(self $other): self
    {
        return new self([...array_keys($this->domains), ...array_keys($other->domains)]);
    }

    public function isEmpty(): bool
    {
        return [] === $this->domains;
    }

    public function count(): int
    {
        return \count($this->domains);
    }

    public function contains(string $asciiDomain): bool
    {
        $domain = $asciiDomain;

        while (true) {
            if (isset($this->domains[$domain])) {
                return true;
            }

            $dot = strpos($domain, '.');

            if (false === $dot) {
                return false;
            }

            $domain = substr($domain, $dot + 1);
        }
    }
}
