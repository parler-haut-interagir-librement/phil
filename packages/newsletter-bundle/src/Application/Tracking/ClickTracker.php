<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Tracking;

use Phil\NewsletterBundle\Application\Event\LinkClicked;
use Phil\NewsletterBundle\Application\Exception\LinkRefused;
use Phil\NewsletterBundle\Application\Exception\NotFound;
use Phil\NewsletterBundle\Application\SignedLinks;
use Phil\NewsletterBundle\Domain\Contract\CampaignRepository;
use Phil\NewsletterBundle\Domain\Contract\DeliveryRepository;
use Phil\NewsletterBundle\Domain\Contract\EngagementRepository;
use Phil\NewsletterBundle\Domain\Enum\ClickTracking;
use Phil\NewsletterBundle\Domain\Enum\LinkPurpose;
use Phil\NewsletterBundle\Domain\Model\Campaign;
use Phil\NewsletterBundle\Domain\Model\Delivery;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Follows the tracked link of a message: it redirects to the address the campaign holds for it — never to an address
 * the link itself carries — and counts the click, in the link's total, and for the recipient too when the campaign
 * chose to count clicks per recipient.
 *
 * Machines that open links before a person does — the scanners of mail providers, previews — are left out of the
 * counts without reading an IP address or a user agent: a `HEAD` or `OPTIONS` request, a request that says it is a
 * prefetch, and a click within seconds of the message being sent are not counted. The redirect happens all the same.
 */
final readonly class ClickTracker
{
    public const int DEFAULT_MINIMUM_SECONDS = 10;

    public function __construct(
        private SignedLinks $links,
        private CampaignRepository $campaigns,
        private DeliveryRepository $deliveries,
        private EngagementRepository $engagement,
        private EventDispatcherInterface $events,
        private ClockInterface $clock,
        private int $minimumSeconds = self::DEFAULT_MINIMUM_SECONDS,
    ) {
    }

    /**
     * The tracked address of a link of a campaign's message.
     */
    public function token(Campaign $campaign, string $url, ?Delivery $delivery): string
    {
        $index = $this->campaigns->linkIndex($campaign->id, $url);
        $recipient = ClickTracking::PerRecipient === $campaign->clickTracking && $delivery instanceof Delivery ? $delivery->id : '';

        return $this->links->sign(LinkPurpose::Click, $campaign->id, $index.':'.$recipient);
    }

    /**
     * @param array<string, string|null> $headers the request's headers, by lowercase name
     *
     * @throws LinkRefused when the link is not one of the newsletter's
     * @throws NotFound    when the campaign holds no such link
     */
    public function follow(string $token, string $method, array $headers): string
    {
        $link = $this->links->verify($token, LinkPurpose::Click);
        [$index, $recipient] = explode(':', $link->extra, 2) + [1 => ''];

        if (1 !== preg_match('/^(0|[1-9][0-9]{0,5})$/D', $index)) {
            throw LinkRefused::because('The link is for something else.');
        }

        $url = $this->campaigns->link($link->subject, (int) $index) ?? throw NotFound::of('link', $link->subject.'#'.$index);

        if ($this->counts($method, $headers, $link->issuedAt)) {
            $this->count($link->subject, (int) $index, $recipient);
        }

        return $url;
    }

    /**
     * @param array<string, string|null> $headers
     */
    public function counts(string $method, array $headers, int $issuedAt): bool
    {
        if ('GET' !== strtoupper($method)) {
            return false;
        }

        foreach (['sec-purpose', 'purpose', 'x-purpose', 'x-moz'] as $name) {
            if (str_contains(strtolower($headers[$name] ?? ''), 'prefetch') || str_contains(strtolower($headers[$name] ?? ''), 'preview')) {
                return false;
            }
        }

        return $this->clock->now()->getTimestamp() - $issuedAt >= $this->minimumSeconds;
    }

    private function count(string $campaignId, int $index, string $recipient): void
    {
        $this->engagement->countClick($campaignId, $index);
        $campaign = '' === $recipient ? null : $this->campaigns->get($campaignId);

        if (!$campaign instanceof Campaign || ClickTracking::PerRecipient !== $campaign->clickTracking) {
            return;
        }

        $delivery = $this->deliveries->get($recipient);

        if ($delivery instanceof Delivery && $delivery->campaignId === $campaignId) {
            $now = $this->clock->now();
            $this->engagement->recordRecipientClick($campaignId, $index, $delivery->subscriberId, $now);
            $this->events->dispatch(new LinkClicked($campaignId, $index, $delivery->subscriberId, $now));
        }
    }
}
