<?php

declare(strict_types=1);

namespace Phil\EmailAddress;

use Phil\EmailAddress\Exception\InvalidDomainList;
use Phil\EmailAddress\Internal\DomainSet;

/**
 * The domains of disposable mailboxes, which receive mail for a few minutes and then vanish, and their subdomains.
 *
 * `bundled()` reads the list imported in `data/` by `Data\DisposableDomainsImporter`; `of()` takes the application's
 * own; `merge()` adds one to the other.
 */
final readonly class DisposableDomains
{
    private function __construct(
        private DomainSet $set,
    ) {
    }

    /**
     * The list imported in the library's `data/`, whose source and release `data/VERSION` names.
     */
    public static function bundled(): self
    {
        /** @var list<string> $domains */
        $domains = require \dirname(__DIR__).'/data/disposable-domains.php';

        return new self(new DomainSet($domains));
    }

    /**
     * @param iterable<string> $domains domain names, in any script and case
     *
     * @throws InvalidDomainList when one is not a domain name
     */
    public static function of(iterable $domains): self
    {
        return new self(DomainSet::of($domains));
    }

    public function merge(self $other): self
    {
        return new self($this->set->union($other->set));
    }

    public function contains(EmailAddress|string $addressOrAsciiDomain): bool
    {
        return $this->set->contains(
            $addressOrAsciiDomain instanceof EmailAddress ? $addressOrAsciiDomain->asciiDomain : $addressOrAsciiDomain,
        );
    }

    public function count(): int
    {
        return $this->set->count();
    }
}
