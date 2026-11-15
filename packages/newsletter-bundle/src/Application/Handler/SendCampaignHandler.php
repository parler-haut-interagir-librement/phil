<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Handler;

use Phil\NewsletterBundle\Application\Contract\SegmentResolver;
use Phil\NewsletterBundle\Application\Message\SendCampaign;
use Phil\NewsletterBundle\Application\Message\SendDelivery;
use Phil\NewsletterBundle\Domain\Contract\CampaignRepository;
use Phil\NewsletterBundle\Domain\Contract\DeliveryRepository;
use Phil\NewsletterBundle\Domain\Enum\CampaignStatus;
use Phil\NewsletterBundle\Domain\Model\Campaign;
use Phil\NewsletterBundle\Domain\Model\Delivery;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Queues the messages of a campaign: one delivery per recipient the segment resolver gives, created once — its id is
 * drawn from the campaign and the subscriber —, and a `SendDelivery` for each new one. The campaign is sent once every
 * delivery is queued; handling the message again queues nothing twice.
 */
#[AsMessageHandler]
final readonly class SendCampaignHandler
{
    public function __construct(
        private CampaignRepository $campaigns,
        private DeliveryRepository $deliveries,
        private SegmentResolver $recipients,
        private MessageBusInterface $bus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SendCampaign $message): void
    {
        $campaign = $this->campaigns->get($message->campaignId);

        if (!$campaign instanceof Campaign) {
            return;
        }

        if (CampaignStatus::Draft === $campaign->status || CampaignStatus::Scheduled === $campaign->status) {
            $campaign->start();
            $this->campaigns->save($campaign);
        }

        if (CampaignStatus::Sending !== $campaign->status) {
            return;
        }

        $now = $this->clock->now();

        foreach ($this->recipients->recipients($campaign, $now) as $subscriberId) {
            $delivery = new Delivery(Delivery::idFor($campaign->id, $subscriberId), $subscriberId, $campaign->id, null, $now);

            if ($this->deliveries->create($delivery)) {
                $this->bus->dispatch(new SendDelivery($delivery->id));
            }
        }

        $campaign->finish($this->clock->now());
        $this->campaigns->save($campaign);
    }
}
