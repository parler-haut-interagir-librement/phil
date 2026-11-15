<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Message;

/**
 * Asks a worker to queue the messages of a campaign. It carries identifiers only: what it is about is read from the store when it is handled.
 */
final readonly class SendCampaign
{
    public function __construct(
        public string $campaignId,
    ) {
    }
}
