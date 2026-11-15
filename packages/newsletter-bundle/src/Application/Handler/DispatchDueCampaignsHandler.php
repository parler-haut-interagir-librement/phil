<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Handler;

use Phil\NewsletterBundle\Application\Campaigns;
use Phil\NewsletterBundle\Application\Message\DispatchDueCampaigns;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Starts the scheduled campaigns whose time has come.
 */
#[AsMessageHandler]
final readonly class DispatchDueCampaignsHandler
{
    public function __construct(
        private Campaigns $campaigns,
    ) {
    }

    public function __invoke(DispatchDueCampaigns $message): void
    {
        $this->campaigns->startDue();
    }
}
