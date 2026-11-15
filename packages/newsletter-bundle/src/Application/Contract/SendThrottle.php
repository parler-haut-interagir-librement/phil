<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Contract;

use Phil\NewsletterBundle\Application\Sending\SenderProfile;

/**
 * The pace of sending: per recipient domain, and the daily ceiling of a profile.
 */
interface SendThrottle
{
    /**
     * Takes a place for one message to a domain from a profile, and returns 0; or, when none is free, the seconds to
     * wait before asking again, taking nothing.
     */
    public function wait(SenderProfile $profile, string $domain): int;
}
