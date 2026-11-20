<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application;

use DateTimeImmutable;
use Phil\NewsletterBundle\Application\Event\CampaignApproved;
use Phil\NewsletterBundle\Application\Exception\ApprovalRefused;
use Phil\NewsletterBundle\Application\Exception\NotFound;
use Phil\NewsletterBundle\Application\Message\SendCampaign;
use Phil\NewsletterBundle\Domain\Contract\CampaignRepository;
use Phil\NewsletterBundle\Domain\Contract\DeliveryRepository;
use Phil\NewsletterBundle\Domain\Contract\EngagementRepository;
use Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue;
use Phil\NewsletterBundle\Domain\Model\Campaign;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Approving a campaign, sending it now or later under a send run, cancelling it, and what it gave: its deliveries by
 * state and its clicks by link.
 *
 * Under the approval policy, a campaign is sent once a user of the approving role approved it; with the four-eyes rule,
 * that user is not its author. A scheduled campaign whose time has come waits for its approval.
 */
final readonly class Campaigns
{
    public function __construct(
        private CampaignRepository $campaigns,
        private DeliveryRepository $deliveries,
        private EngagementRepository $engagement,
        private MessageBusInterface $bus,
        private ClockInterface $clock,
        private SendRuns $runs,
        private ApprovalPolicy $approval = new ApprovalPolicy(),
        private ?AuthorizationCheckerInterface $authorization = null,
        private ?TokenStorageInterface $tokens = null,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    public function get(string $id): Campaign
    {
        return $this->campaigns->get($id) ?? throw NotFound::of('campaign', $id);
    }

    /**
     * The signed-in user approves the campaign, as it stands, for sending.
     *
     * @throws ApprovalRefused        when the user lacks the approving role, is not signed in, or is its author under the four-eyes rule
     * @throws InvalidNewsletterValue when the campaign is neither a draft nor scheduled
     */
    public function approve(Campaign $campaign): void
    {
        if (true !== $this->authorization?->isGranted($this->approval->role)) {
            throw ApprovalRefused::notGranted($this->approval->role);
        }

        $approver = $this->tokens?->getToken()?->getUserIdentifier() ?? '';

        if ('' === $approver) {
            throw ApprovalRefused::anonymous();
        }

        if ($this->approval->fourEyes && (null === $campaign->author || $approver === $campaign->author)) {
            throw ApprovalRefused::fourEyes();
        }

        $now = $this->clock->now();
        $campaign->approve($approver, $now, $this->approval->fourEyes);
        $this->campaigns->save($campaign);
        $this->events?->dispatch(new CampaignApproved($campaign->id, $now));
    }

    public function withdrawApproval(Campaign $campaign): void
    {
        $campaign->withdrawApproval();
        $this->campaigns->save($campaign);
    }

    /**
     * Starts a send run of the campaign and asks a worker to queue its messages.
     *
     * @throws InvalidNewsletterValue when the campaign is neither a draft nor scheduled, or awaits its approval
     */
    public function sendNow(Campaign $campaign): void
    {
        $campaign->start($this->approval->required);
        $this->campaigns->save($campaign);
        $run = $this->runs->start($campaign);
        $this->bus->dispatch(new SendCampaign($campaign->id, $run->id));
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
     * Starts the scheduled campaigns whose time has come, once approved.
     *
     * @return int how many were started
     */
    public function startDue(): int
    {
        $started = 0;

        foreach ($this->campaigns->due($this->clock->now()) as $campaign) {
            if ($this->approval->required && !$campaign->isApproved()) {
                continue;
            }

            $this->sendNow($campaign);
            ++$started;
        }

        return $started;
    }

    /**
     * @return array{deliveries: array<string, int>, clicks: array<int, int>}
     */
    public function results(string $campaignId): array
    {
        return ['deliveries' => $this->deliveries->countByStatus($campaignId), 'clicks' => $this->engagement->clicks($campaignId)];
    }
}
