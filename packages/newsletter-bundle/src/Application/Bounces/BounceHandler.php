<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Bounces;

use Phil\BounceParser\BounceAction;
use Phil\BounceParser\BounceKind;
use Phil\BounceParser\BounceParser;
use Phil\BounceParser\Verp;
use Phil\NewsletterBundle\Application\Contract\CurrentSite;
use Phil\NewsletterBundle\Application\Subscriptions;
use Phil\NewsletterBundle\Domain\Contract\DeliveryRepository;
use Phil\NewsletterBundle\Domain\Contract\SubscriberRepository;
use Phil\NewsletterBundle\Domain\Contract\SuppressionRepository;
use Phil\NewsletterBundle\Domain\Enum\AudienceEventType;
use Phil\NewsletterBundle\Domain\Model\Delivery;
use Phil\NewsletterBundle\Domain\Model\Subscriber;
use Psr\Clock\ClockInterface;
use SensitiveParameter;

/**
 * What a bounce does to the subscriber it is about, whether it came back to the bounce mailbox — read by
 * `phil/bounce-parser`: delivery status notifications, abuse reports, bounces written as text, automatic replies —
 * or from a provider's webhook.
 *
 * The delivery is found by the signed VERP tag of the return path, else the subscriber by the failed address on the
 * current site. A hard bounce or a complaint suppresses the address; soft bounces are counted, and the address is
 * suppressed at the threshold; a request to leave unsubscribes; anything else changes nothing.
 */
final readonly class BounceHandler
{
    public function __construct(
        private BounceParser $parser,
        private SubscriberRepository $subscribers,
        private DeliveryRepository $deliveries,
        private SuppressionRepository $suppressions,
        private Subscriptions $subscriptions,
        private CurrentSite $site,
        private ClockInterface $clock,
        private ?Verp $verp = null,
        #[SensitiveParameter]
        private string $verpKey = '',
        private int $softBounceThreshold = 3,
    ) {
    }

    /**
     * @return list<BounceOutcome> what the message did to each subscriber it names
     */
    public function handleMessage(string $raw): array
    {
        $report = $this->parser->parse($raw);
        $kind = match ($report->action) {
            BounceAction::Suppress, BounceAction::CountHard => BounceKind::Complaint === $report->kind ? BounceKind::Complaint : BounceKind::Hard,
            BounceAction::CountSoft => BounceKind::Soft,
            BounceAction::Unsubscribe => BounceKind::Feedback,
            BounceAction::Ignore, BounceAction::Review => null,
        };

        if (null === $kind) {
            return [BounceOutcome::Ignored];
        }

        $deliveryId = $this->verp instanceof Verp ? $this->verp->decodeSigned($report->verpTag, $this->verpKey) : null;
        $delivery = null === $deliveryId ? null : $this->deliveries->get($deliveryId);

        if ($delivery instanceof Delivery) {
            return [$this->apply($kind, $this->subscribers->get($delivery->subscriberId), $delivery)];
        }

        $outcomes = [];

        foreach ($report->addresses() as $address) {
            $outcomes[] = $this->apply($kind, $this->subscribers->byEmail($this->site->site(), Subscriber::keyOf($address)), null);
        }

        return [] === $outcomes ? [BounceOutcome::Ignored] : $outcomes;
    }

    /**
     * A provider's event: `Hard`, `Soft`, `Complaint`, or `Feedback` for a request to leave.
     */
    public function handleEvent(BounceKind $kind, string $email, ?string $deliveryId): BounceOutcome
    {
        $delivery = null === $deliveryId ? null : $this->deliveries->get($deliveryId);
        $subscriber = $delivery instanceof Delivery ? $this->subscribers->get($delivery->subscriberId) : $this->subscribers->byEmail($this->site->site(), Subscriber::keyOf($email));

        return $this->apply($kind, $subscriber, $delivery);
    }

    private function apply(BounceKind $kind, ?Subscriber $subscriber, ?Delivery $delivery): BounceOutcome
    {
        if (!$subscriber instanceof Subscriber) {
            return BounceOutcome::Ignored;
        }

        $now = $this->clock->now();

        if (BounceKind::Feedback === $kind) {
            return $this->subscriptions->leave($subscriber->id) ? BounceOutcome::Unsubscribed : BounceOutcome::Ignored;
        }

        $outcome = match ($kind) {
            BounceKind::Complaint => BounceOutcome::Complained,
            BounceKind::Hard => BounceOutcome::Bounced,
            BounceKind::Soft => $subscriber->softBounce($this->softBounceThreshold) ? BounceOutcome::Bounced : BounceOutcome::Counted,
            default => BounceOutcome::Ignored,
        };

        if (BounceOutcome::Ignored === $outcome) {
            return $outcome;
        }

        if (BounceOutcome::Complained === $outcome) {
            $subscriber->complained();
        } elseif (BounceOutcome::Bounced === $outcome) {
            $subscriber->bounced();
        }

        $this->subscribers->save($subscriber);

        if ($delivery instanceof Delivery && BounceOutcome::Counted !== $outcome) {
            $delivery->bounced();
            $this->deliveries->save($delivery);
        }

        if (BounceOutcome::Counted !== $outcome) {
            $this->suppressions->suppress($subscriber->site, $subscriber->emailKey(), $subscriber->status->value);
            $this->subscriptions->announce(BounceOutcome::Complained === $outcome ? AudienceEventType::Complained : AudienceEventType::Bounced, $subscriber, null, $now);
        }

        return $outcome;
    }
}
