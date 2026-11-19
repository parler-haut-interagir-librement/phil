<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Contract;

use Phil\GuardianConsent\CalendarDate;
use Phil\GuardianConsent\MemberId;
use Phil\GuardianConsent\Minor;

/**
 * Where the members below the age of consent are kept, one record per member.
 */
interface MinorStore
{
    public function get(MemberId $member): ?Minor;

    /**
     * Keeps the record, replacing the one of the same member.
     */
    public function save(Minor $minor): void;

    /**
     * Forgets the member's record; nothing happens when there is none.
     */
    public function remove(MemberId $member): void;

    /**
     * The members who reach the age of consent on `$date` or before and were not yet told of the handover, the
     * earliest first, at most `$limit`.
     *
     * @param positive-int $limit
     *
     * @return list<Minor>
     */
    public function dueForHandover(CalendarDate $date, int $limit): array;
}
