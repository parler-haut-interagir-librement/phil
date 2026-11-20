<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Contract;

/**
 * The port that gives the canonical form of an address: two addresses with one canonical form reach one mailbox, so
 * that one is enough for an account, and a canonical form belongs to one account for each purpose.
 *
 * It is given an address normalized by `Account::normalizeEmail()` — trimmed, in lower case — and answers a string of
 * at most 180 characters. Its answer for an address never changes once accounts are stored: a new rule needs the
 * stored forms computed again.
 */
interface AddressCanonicalizer
{
    public function canonical(string $normalized): string;
}
