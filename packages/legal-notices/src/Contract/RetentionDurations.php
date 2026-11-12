<?php

declare(strict_types=1);

namespace Phil\LegalNotices\Contract;

use Phil\LegalNotices\RetentionDuration;

/**
 * The retention periods a privacy policy lists, read from wherever the site keeps its retention schedule.
 */
interface RetentionDurations
{
    /**
     * @return list<RetentionDuration> in the order the policy lists them
     */
    public function durations(): array;
}
