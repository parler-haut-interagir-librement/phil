<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application;

use DateTimeImmutable;
use Phil\NewsletterBundle\Application\Exception\NotFound;
use Phil\NewsletterBundle\Application\Message\SendCampaign;
use Phil\NewsletterBundle\Domain\Contract\CampaignRepository;
use Phil\NewsletterBundle\Domain\Contract\DeliveryRepository;
use Phil\NewsletterBundle\Domain\Contract\EngagementRepository;
use Phil\NewsletterBundle\Domain\Model\Campaign;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Sending a campaign now or later, cancelling it, and what it gave: its deliveries by state and its clicks by link.
 */
final readonly class Campaigns
{
    public function __construct(
        private CampaignRepository $campaigns,
        private DeliveryRepository $deliveries,
        private EngagementRepository $engagement,
        private MessageBusInterface $bus,
        private ClockInterface $clock,
    ) {
    }

    public function get(string $id): Campaign
    {
        return $this->campaigns->get($id) ?? throw NotFound::of('campaign', $id);
    }

    public function sendNow(Campaign $campaign): void
    {
        $campaign->start();
        $this->campaigns->save($campaign);
        $this->bus->dispatch(new SendCampaign($campaign->id));
    }

    public function schedule(Campaign $campaign, DateTimeImmutable $at): void
    {
        $campaign->schedule($at);
        $this->campaigns->save($campaign);
    }

    public function cancel(Campaign $campaign): void
    {
        $campaign->cancel();
        $this->campaigns->save($campaign);
    }

    /**
     * Starts the scheduled campaigns whose time has come.
     *
     * @return int how many were started
     */
    public function startDue(): int
    {
        $due = $this->campaigns->due($this->clock->now());

        foreach ($due as $campaign) {
            $this->sendNow($campaign);
        }

        return \count($due);
    }

    /**
     * @return array{deliveries: array<string, int>, clicks: array<int, int>}
     */
    public function results(string $campaignId): array
    {
        return ['deliveries' => $this->deliveries->countByStatus($campaignId), 'clicks' => $this->engagement->clicks($campaignId)];
    }
}
