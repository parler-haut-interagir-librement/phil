<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Message;

/**
 * Asks a worker to queue the messages of a campaign, under the send run that sends them. It carries identifiers only:
 * what it is about is read from the store when it is handled.
 */
final readonly class SendCampaign
{
    /**
     * @param string|null $runId `null` to queue under the campaign's run still queuing, or a new one
     */
    public function __construct(
        public string $campaignId,
        public ?string $runId = null,
    ) {
    }
}
