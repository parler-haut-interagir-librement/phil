<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Rdf;

/**
 * The identifier issuer of RDFC-1.0: a prefix, a counter, and the identifiers issued so far in the order they were
 * issued. Cloned where the algorithm copies it.
 *
 * @internal
 */
final class IdentifierIssuer
{
    /**
     * @var array<string, string> the issued identifiers by blank node, in the order they were issued
     */
    public array $issued = [];

    public function __construct(
        private readonly string $prefix,
    ) {
    }

    public function issue(string $blank): string
    {
        return $this->issued[$blank] ??= $this->prefix.\count($this->issued);
    }

    public function has(string $blank): bool
    {
        return isset($this->issued[$blank]);
    }
}
