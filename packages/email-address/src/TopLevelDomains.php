<?php

declare(strict_types=1);

namespace Phil\EmailAddress;

use Phil\EmailAddress\Exception\InvalidDomainList;
use Phil\EmailAddress\Internal\TopLevelLabel;

/**
 * The top-level domains the root zone delegates, such as `com`, `ca` or `xn--p1ai`: a domain under any other one
 * receives no mail.
 *
 * `bundled()` reads the IANA list imported in `data/` by `Data\TopLevelDomainsImporter`; `of()` takes the
 * application's own. A list with no domain is empty: `EmailValidator` then checks nothing, as before the first import.
 */
final readonly class TopLevelDomains
{
    /**
     * @param array<string, int> $labels
     */
    private function __construct(
        private array $labels,
    ) {
    }

    /**
     * The list imported in the library's `data/`, whose source and release `data/VERSION` names.
     */
    public static function bundled(): self
    {
        /** @var list<string> $labels */
        $labels = require \dirname(__DIR__).'/data/top-level-domains.php';

        return self::set($labels);
    }

    /**
     * @param iterable<string> $labels top-level domains in ASCII, letters or an `xn--` form, in any case
     *
     * @throws InvalidDomainList when one cannot be a top-level domain
     */
    public static function of(iterable $labels): self
    {
        $set = [];

        foreach ($labels as $label) {
            $set[] = TopLevelLabel::normalize($label) ?? throw InvalidDomainList::domain($label, SyntaxError::InvalidTopLevelDomain);
        }

        return self::set($set);
    }

    /**
     * @param list<string> $labels checked labels in lowercase
     */
    private static function set(array $labels): self
    {
        return new self(array_flip($labels));
    }

    /**
     * Whether the last label of the address's domain, or of an ASCII domain, is delegated. An IP literal has none.
     */
    public function contains(EmailAddress|string $addressOrAsciiDomain): bool
    {
        $domain = $addressOrAsciiDomain instanceof EmailAddress ? $addressOrAsciiDomain->asciiDomain : $addressOrAsciiDomain;
        $dot = strrpos($domain, '.');

        return isset($this->labels[strtolower(false === $dot ? $domain : substr($domain, $dot + 1))]);
    }

    public function isEmpty(): bool
    {
        return [] === $this->labels;
    }

    public function count(): int
    {
        return \count($this->labels);
    }
}
