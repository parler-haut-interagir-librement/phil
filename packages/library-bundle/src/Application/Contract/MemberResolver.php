<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Application\Contract;

/**
 * The member the current request is for, by the application's stable identifier — an account number, a card number —,
 * never an e-mail address. The bundle has no default: the application says how its users are members.
 */
interface MemberResolver
{
    /**
     * The member's identifier, or null when the request is nobody's.
     */
    public function current(): ?string;
}
