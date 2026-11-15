<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Contract;

use DateTimeImmutable;
use Phil\NewsletterBundle\Domain\Model\Campaign;

/**
 * Chooses the recipients of a campaign: every subscriber of its list who may receive, or those of the segment it names.
 *
 * The bundle's own resolver reads the list and refuses a campaign that names a segment; an audience package brings a
 * resolver that knows segments.
 */
interface SegmentResolver
{
    /**
     * The ids of the subscribers to send to, each once. They are checked again when each message is sent: a subscriber
     * who left in between is skipped.
     *
     * @return iterable<string>
     */
    public function recipients(Campaign $campaign, DateTimeImmutable $now): iterable;
}
