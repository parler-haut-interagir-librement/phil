<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Event;

use DateTimeImmutable;

/**
 * A campaign was approved for sending, once saved. It carries identifiers only: who approved it is read from the
 * campaign.
 */
final readonly class CampaignApproved
{
    public function __construct(
        public string $campaignId,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
