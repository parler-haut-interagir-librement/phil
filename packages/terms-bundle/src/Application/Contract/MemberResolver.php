<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Application\Contract;

/**
 * Who the current request is for: the stable identifier of the signed-in member, or `null` for a visitor. The gate
 * leaves a visitor alone.
 */
interface MemberResolver
{
    public function current(): ?string;
}
