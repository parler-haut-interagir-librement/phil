<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Application;

use Phil\NewsletterBundle\Application\Event\LinkClicked;
use Phil\NewsletterBundle\Application\Exception\LinkRefused;
use Phil\NewsletterBundle\Application\Exception\NotFound;
use Phil\NewsletterBundle\Application\Tracking\ClickTracker;
use Phil\NewsletterBundle\Domain\Enum\ClickTracking;
use Phil\NewsletterBundle\Domain\Enum\LinkPurpose;
use Phil\NewsletterBundle\Domain\Model\Campaign;
use Phil\NewsletterBundle\Domain\Model\Delivery;
use Phil\NewsletterBundle\Domain\ValueObject\Site;
use Phil\NewsletterBundle\Tests\Unit\Fixture\Newsletter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClickTracker::class)]
#[CoversClass(LinkClicked::class)]
final class ClickTrackerTest extends TestCase
{
    private Newsletter $newsletter;

    private ClickTracker $tracker;

    protected function setUp(): void
    {
        $this->newsletter = new Newsletter();
        $this->tracker = new ClickTracker($this->newsletter->links, $this->newsletter->campaigns, $this->newsletter->deliveries, $this->newsletter->engagement, $this->newsletter->events, $this->newsletter->clock);
    }

    public function testAnAggregateClickIsCountedWithoutTheRecipient(): void
    {
        $campaign = $this->campaign(ClickTracking::Aggregate);
        $delivery = new Delivery('d1', 's1', 'c1', null, $this->newsletter->clock->now());
        $first = $this->tracker->token($campaign, 'https://example.com/a', $delivery);
        $second = $this->tracker->token($campaign, 'https://example.com/b', $delivery);

        self::assertSame('0:', $this->newsletter->links->verify($first, LinkPurpose::Click)->extra);
        self::assertSame('1:', $this->newsletter->links->verify($second, LinkPurpose::Click)->extra);
        self::assertSame('0:', $this->newsletter->links->verify($this->tracker->token($campaign, 'https://example.com/a', null), LinkPurpose::Click)->extra);

        $this->newsletter->clock->modify('+10 seconds');
        self::assertSame('https://example.com/a', $this->tracker->follow($first, 'GET', []));
        self::assertSame('https://example.com/a', $this->tracker->follow($first, 'get', []));
        self::assertSame('https://example.com/b', $this->tracker->follow($second, 'GET', []));
        self::assertSame([0 => 2, 1 => 1], $this->newsletter->engagement->clicks('c1'));
        self::assertSame([], $this->newsletter->engagement->recipientClicks('s1'));
        self::assertSame([], $this->newsletter->events->events);
    }

    public function testMachinesAreNotCounted(): void
    {
        $campaign = $this->campaign(ClickTracking::Aggregate);
        $token = $this->tracker->token($campaign, 'https://example.com/a', null);

        $this->newsletter->clock->modify('+9 seconds');
        self::assertSame('https://example.com/a', $this->tracker->follow($token, 'GET', []));
        $this->newsletter->clock->modify('+1 second');
        $this->tracker->follow($token, 'HEAD', []);
        $this->tracker->follow($token, 'OPTIONS', []);

        foreach (['sec-purpose' => 'prefetch;prerender', 'purpose' => 'Prefetch', 'x-purpose' => 'preview', 'x-moz' => 'prefetch'] as $header => $value) {
            self::assertFalse($this->tracker->counts('GET', [$header => $value], $this->newsletter->clock->now()->getTimestamp() - 60));
            $this->tracker->follow($token, 'GET', [$header => $value]);
        }

        self::assertSame([], $this->newsletter->engagement->clicks('c1'));
        self::assertTrue($this->tracker->counts('GET', ['purpose' => null, 'sec-purpose' => 'other'], $this->newsletter->clock->now()->getTimestamp() - 10));
        self::assertFalse(new ClickTracker($this->newsletter->links, $this->newsletter->campaigns, $this->newsletter->deliveries, $this->newsletter->engagement, $this->newsletter->events, $this->newsletter->clock, 60)->counts('GET', [], $this->newsletter->clock->now()->getTimestamp() - 59));
    }

    public function testAPerRecipientClickNamesTheSubscriberAndStartsAutomations(): void
    {
        $campaign = $this->campaign(ClickTracking::PerRecipient);
        $delivery = new Delivery('d1', 's1', 'c1', null, $this->newsletter->clock->now());
        $this->newsletter->deliveries->create($delivery);
        $this->newsletter->deliveries->create(new Delivery('d2', 's2', 'other', null, $this->newsletter->clock->now()));

        $token = $this->tracker->token($campaign, 'https://example.com/a', $delivery);
        self::assertSame('0:d1', $this->newsletter->links->verify($token, LinkPurpose::Click)->extra);
        $otherCampaign = $this->newsletter->links->sign(LinkPurpose::Click, 'c1', '0:d2');
        $missing = $this->newsletter->links->sign(LinkPurpose::Click, 'c1', '0:missing');
        $this->newsletter->clock->modify('+1 minute');

        $this->tracker->follow($token, 'GET', []);
        $this->tracker->follow($otherCampaign, 'GET', []);
        $this->tracker->follow($missing, 'GET', []);

        $clicks = $this->newsletter->engagement->recipientClicks('s1');
        self::assertCount(1, $clicks);
        self::assertSame(['c1', 0], [$clicks[0]['campaign'], $clicks[0]['link']]);
        self::assertSame([], $this->newsletter->engagement->recipientClicks('s2'));
        self::assertEquals([new LinkClicked('c1', 0, 's1', $this->newsletter->clock->now())], $this->newsletter->events->events);
        self::assertSame([0 => 3], $this->newsletter->engagement->clicks('c1'));
    }

    public function testARecipientIsIgnoredWhenTheCampaignCountsInATotal(): void
    {
        $this->campaign(ClickTracking::Aggregate);
        $this->newsletter->campaigns->linkIndex('c1', 'https://example.com/a');
        $this->newsletter->deliveries->create(new Delivery('d1', 's1', 'c1', null, $this->newsletter->clock->now()));

        $token = $this->newsletter->links->sign(LinkPurpose::Click, 'c1', '0:d1');
        $this->newsletter->clock->modify('+1 minute');

        $this->tracker->follow($token, 'GET', []);

        self::assertSame([], $this->newsletter->engagement->recipientClicks('s1'));
        self::assertSame([0 => 1], $this->newsletter->engagement->clicks('c1'));
    }

    public function testALinkTheCampaignDoesNotHoldIsRefused(): void
    {
        $this->campaign(ClickTracking::Aggregate);

        foreach (['9:', 'x:', '01:', ''] as $extra) {
            try {
                $this->tracker->follow($this->newsletter->links->sign(LinkPurpose::Click, 'c1', $extra), 'GET', []);
                self::fail('Expected a refusal.');
            } catch (NotFound|LinkRefused $refused) {
                self::assertSame('9:' === $extra ? 'No link "c1#9".' : 'The link is for something else.', $refused->getMessage());
            }
        }

        $this->expectException(LinkRefused::class);
        $this->tracker->follow($this->newsletter->links->sign(LinkPurpose::Unsubscribe, 'c1', '0:'), 'GET', []);
    }

    private function campaign(ClickTracking $tracking): Campaign
    {
        $campaign = new Campaign('c1', Site::default(), 'l', 'S', 'B', clickTracking: $tracking);
        $this->newsletter->campaigns->save($campaign);

        return $campaign;
    }
}
