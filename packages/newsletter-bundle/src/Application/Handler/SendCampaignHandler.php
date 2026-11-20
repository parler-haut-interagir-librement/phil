<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Handler;

use Phil\NewsletterBundle\Application\Contract\SegmentResolver;
use Phil\NewsletterBundle\Application\Message\SendCampaign;
use Phil\NewsletterBundle\Application\Message\SendDelivery;
use Phil\NewsletterBundle\Application\SendRuns;
use Phil\NewsletterBundle\Domain\Contract\CampaignRepository;
use Phil\NewsletterBundle\Domain\Contract\DeliveryRepository;
use Phil\NewsletterBundle\Domain\Enum\CampaignStatus;
use Phil\NewsletterBundle\Domain\Enum\SendRunStatus;
use Phil\NewsletterBundle\Domain\Model\Campaign;
use Phil\NewsletterBundle\Domain\Model\Delivery;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Queues the messages of a campaign under its send run: one delivery per recipient the segment resolver gives, created
 * once — its id is drawn from the campaign and the subscriber, so that no run sends twice to one recipient —, and a
 * `SendDelivery` for each new one. The campaign is sent once every delivery is queued, and the run knows how many it
 * holds; handling the message again queues nothing twice. A campaign that awaits its approval is not started.
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
        private SendRuns $runs,
        private bool $approvalRequired = true,
    ) {
    }

    public function __invoke(SendCampaign $message): void
    {
        $campaign = $this->campaigns->get($message->campaignId);

        if (!$campaign instanceof Campaign) {
            return;
        }

        if (CampaignStatus::Draft === $campaign->status || CampaignStatus::Scheduled === $campaign->status) {
            if ($this->approvalRequired && !$campaign->isApproved()) {
                return;
            }

            $campaign->start($this->approvalRequired);
            $this->campaigns->save($campaign);
        }

        if (CampaignStatus::Sending !== $campaign->status) {
            return;
        }

        $run = null === $message->runId ? null : $this->runs->find($message->runId);
        $run ??= $this->runs->current($campaign);
        $now = $this->clock->now();

        if (SendRunStatus::Cancelled !== $run->status) {
            foreach ($this->recipients->recipients($campaign, $now) as $subscriberId) {
                $delivery = new Delivery(Delivery::idFor($campaign->id, $subscriberId), $subscriberId, $campaign->id, null, $now, $run->id);

                if ($this->deliveries->create($delivery)) {
                    $this->bus->dispatch(new SendDelivery($delivery->id));
                }
            }

            $this->runs->queuedAll($run->id);
        }

        $campaign->finish($this->clock->now());
        $this->campaigns->save($campaign);
    }
}
