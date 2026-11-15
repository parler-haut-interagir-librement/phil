<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Application\Contract;

/**
 * Turns the address a request came from into what an acceptance keeps of it — a network, a keyed digest —, never the
 * address itself; `null` when nothing is kept.
 */
interface AddressMasker
{
    public function mask(string $address): ?string;
}
