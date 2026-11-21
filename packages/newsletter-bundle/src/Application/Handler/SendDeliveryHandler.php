<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Handler;

use DateTimeImmutable;
use Phil\NewsletterBundle\Application\Content\ContentRenderer;
use Phil\NewsletterBundle\Application\Content\RenderedMessage;
use Phil\NewsletterBundle\Application\Contract\SendThrottle;
use Phil\NewsletterBundle\Application\Message\SendDelivery;
use Phil\NewsletterBundle\Application\NewsletterUrls;
use Phil\NewsletterBundle\Application\Sending\MessageComposer;
use Phil\NewsletterBundle\Application\Sending\SenderProfiles;
use Phil\NewsletterBundle\Application\SendRuns;
use Phil\NewsletterBundle\Application\SignedLinks;
use Phil\NewsletterBundle\Application\Tracking\ClickTracker;
use Phil\NewsletterBundle\Domain\Contract\CampaignRepository;
use Phil\NewsletterBundle\Domain\Contract\DeliveryRepository;
use Phil\NewsletterBundle\Domain\Contract\MailingListRepository;
use Phil\NewsletterBundle\Domain\Contract\SubscriberRepository;
use Phil\NewsletterBundle\Domain\Contract\SuppressionRepository;
use Phil\NewsletterBundle\Domain\Contract\TemplateRepository;
use Phil\NewsletterBundle\Domain\Enum\CampaignStatus;
use Phil\NewsletterBundle\Domain\Enum\ClickTracking;
use Phil\NewsletterBundle\Domain\Enum\LinkPurpose;
use Phil\NewsletterBundle\Domain\Enum\SendRunCounter;
use Phil\NewsletterBundle\Domain\Enum\SendRunStatus;
use Phil\NewsletterBundle\Domain\Enum\SubscriberStatus;
use Phil\NewsletterBundle\Domain\Model\Campaign;
use Phil\NewsletterBundle\Domain\Model\Delivery;
use Phil\NewsletterBundle\Domain\Model\MailingList;
use Phil\NewsletterBundle\Domain\Model\Membership;
use Phil\NewsletterBundle\Domain\Model\Subscriber;
use Phil\NewsletterBundle\Domain\Model\Template;
use Psr\Clock\ClockInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * Sends one queued message, at its time. The message of a paused send run is held, left queued until an operator
 * resumes the run, and the message of a cancelled run is skipped. The subscriber is checked again first: one who left
 * the list, paused, bounced or complained since the message was queued is skipped. Outside the profile's send window,
 * or past its pace for the recipient's domain or its daily ceiling, the message is sent again later with a delay. A
 * transport failure leaves the delivery to retry and is thrown for Messenger's retries. What became of the message is
 * counted in its send run.
 *
 * The email goes through Symfony Mailer's transports, never `MailerInterface`: with `SendEmailMessage` routed to a
 * Messenger transport, the address and the signed unsubscription link would wait in a second queue, and a refusal
 * would be seen there rather than by this handler.
 */
#[AsMessageHandler]
final readonly class SendDeliveryHandler
{
    public function __construct(
        private DeliveryRepository $deliveries,
        private SubscriberRepository $subscribers,
        private CampaignRepository $campaigns,
        private MailingListRepository $lists,
        private TemplateRepository $templates,
        private SuppressionRepository $suppressions,
        private SenderProfiles $profiles,
        private SendThrottle $throttle,
        private ContentRenderer $renderer,
        private MessageComposer $composer,
        private ClickTracker $clicks,
        private SignedLinks $links,
        private NewsletterUrls $urls,
        private TransportInterface $transport,
        private MessageBusInterface $bus,
        private ClockInterface $clock,
        private ?SendRuns $runs = null,
    ) {
    }

    public function __invoke(SendDelivery $message): void
    {
        $delivery = $this->deliveries->get($message->deliveryId);

        if (!$delivery instanceof Delivery || !$delivery->isPending()) {
            return;
        }

        $run = $this->runs?->status($delivery->runId);

        if (SendRunStatus::Paused === $run) {
            return;
        }

        $now = $this->clock->now();
        $subscriber = $this->subscribers->get($delivery->subscriberId);
        $campaign = null === $delivery->campaignId ? null : $this->campaigns->get($delivery->campaignId);
        $list = $campaign instanceof Campaign ? $this->lists->get($campaign->listId) : null;

        if (SendRunStatus::Cancelled === $run || !$subscriber instanceof Subscriber || !$this->mayReceive($subscriber, $campaign, $list, $now)) {
            $this->skip($delivery);

            return;
        }

        $profile = $this->profiles->get($campaign->senderProfile ?? $list->senderProfile ?? null);

        if (!$profile->window->contains($now)) {
            $this->later($delivery, $profile->window->next($now)->getTimestamp() - $now->getTimestamp());

            return;
        }

        $wait = $this->throttle->wait($profile, substr((string) strrchr($subscriber->email, '@'), 1));

        if ($wait > 0) {
            $this->later($delivery, $wait);

            return;
        }

        $unsubscribe = $this->urls->to(NewsletterUrls::UNSUBSCRIBE, $this->links->sign(LinkPurpose::Unsubscribe, $subscriber->id, $list->id ?? ''), $subscriber->site);
        $rendered = $this->render($delivery, $subscriber, $campaign, $list, $unsubscribe);

        if (!$rendered instanceof RenderedMessage) {
            $this->skip($delivery);

            return;
        }

        try {
            $this->transport->send($this->composer->compose($delivery->id, $subscriber, $profile, $rendered, $unsubscribe, $list, $campaign instanceof Campaign));
        } catch (TransportExceptionInterface $transportException) {
            $delivery->failed();
            $this->deliveries->save($delivery);
            $this->runs?->record($delivery->runId, SendRunCounter::Failed);

            throw $transportException;
        }

        $delivery->sent($this->clock->now());
        $this->deliveries->save($delivery);
        $this->runs?->record($delivery->runId, SendRunCounter::Sent);

        if (SubscriberStatus::Paused === $subscriber->status) {
            $subscriber->resume();
        }

        $subscriber->delivered();
        $this->subscribers->save($subscriber);
    }

    private function mayReceive(Subscriber $subscriber, ?Campaign $campaign, ?MailingList $list, DateTimeImmutable $now): bool
    {
        if (!$subscriber->canReceive($now) || $this->suppressions->isSuppressed($subscriber->site, $subscriber->emailKey())) {
            return false;
        }

        if (!$campaign instanceof Campaign) {
            return true;
        }

        $membership = $list instanceof MailingList ? $this->subscribers->membership($subscriber->id, $list->id) : null;

        return CampaignStatus::Cancelled !== $campaign->status && $membership instanceof Membership && $membership->isActive();
    }

    private function render(Delivery $delivery, Subscriber $subscriber, ?Campaign $campaign, ?MailingList $list, string $unsubscribe): ?RenderedMessage
    {
        $context = [
            'list_name' => $list->name ?? '',
            'site' => $subscriber->site->key,
            'unsubscribe_url' => $unsubscribe,
            'preferences_url' => $this->urls->to(NewsletterUrls::PREFERENCES, $this->links->sign(LinkPurpose::Preferences, $subscriber->id, '', 31_536_000), $subscriber->site),
        ];

        if ($campaign instanceof Campaign) {
            $layout = null === $campaign->template ? null : $this->templates->find($campaign->site, $campaign->template);
            $track = ClickTracking::Off === $campaign->clickTracking ? null : fn (string $url): string => $this->urls->to(NewsletterUrls::CLICK, $this->clicks->token($campaign, $url, $delivery), $subscriber->site);

            return $this->renderer->campaign($campaign->subject, $campaign->preheader, $campaign->body, $layout, $context, $track);
        }

        $template = null === $delivery->template ? null : $this->templates->find($subscriber->site, $delivery->template);

        return $template instanceof Template ? $this->renderer->template($template, $context) : null;
    }

    private function skip(Delivery $delivery): void
    {
        $delivery->skipped();
        $this->deliveries->save($delivery);
        $this->runs?->record($delivery->runId, SendRunCounter::Skipped);
    }

    private function later(Delivery $delivery, int $seconds): void
    {
        $this->bus->dispatch(new SendDelivery($delivery->id), [new DelayStamp(max(1, $seconds) * 1000)]);
    }
}
