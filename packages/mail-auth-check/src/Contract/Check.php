<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Contract;

use Phil\MailAuthCheck\Finding;
use Phil\MailAuthCheck\SenderProfile;

/**
 * One verification of a sender profile against its DNS. `MailAuthCheck` runs its checks in order and gathers their
 * findings; an application adds its own, such as MTA-STS or a provider's own records.
 *
 * A check keeps no state between calls: the resolver it receives serves one check of one profile.
 */
interface Check
{
    /**
     * @return list<Finding> at least one finding, so that the report shows every check ran; never throws for what
     *                       the DNS answers, a failed lookup included
     */
    public function run(SenderProfile $profile, TxtResolver $dns): array;
}
