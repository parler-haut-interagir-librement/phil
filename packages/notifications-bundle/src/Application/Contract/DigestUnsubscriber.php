<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Contract;

/**
 * Records that a recipient stops their digest, from the signed link of a digest: what they chose besides — their quiet
 * hours, the channels they turned off, their locale — stays as it was. Without one, a digest carries no link to
 * unsubscribe.
 */
interface DigestUnsubscriber
{
    /**
     * The recipient gets no digest any more: an email for each notification of a type that allows one, as before
     * they chose a digest. Unsubscribing twice changes nothing.
     */
    public function unsubscribe(string $recipient): void;
}
