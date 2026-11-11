<?php

declare(strict_types=1);

namespace Phil\Listings\Contract;

use DateTimeImmutable;
use Phil\Listings\MemberId;

/**
 * The age of a member, in whole years at an instant, as the application knows it.
 */
interface MemberAges
{
    /**
     * Null when the application does not know it: a listing with a minimum age then refuses the member.
     */
    public function ageOf(MemberId $member, DateTimeImmutable $at): ?int;
}
