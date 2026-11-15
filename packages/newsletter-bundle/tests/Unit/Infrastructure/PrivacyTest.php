<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Infrastructure;

use Phil\NewsletterBundle\Domain\Enum\MembershipStatus;
use Phil\NewsletterBundle\Domain\Model\Delivery;
use Phil\NewsletterBundle\Domain\Model\Membership;
use Phil\NewsletterBundle\Domain\ValueObject\Site;
use Phil\NewsletterBundle\Infrastructure\Privacy\NewsletterPrivacy;
use Phil\NewsletterBundle\Tests\Unit\Fixture\Newsletter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NewsletterPrivacy::class)]
final class PrivacyTest extends TestCase
{
    public function testTheExportListsEverySubscriptionOfTheSubjectAndTheErasureDeletesThem(): void
    {
        $newsletter = new Newsletter();
        $privacy = new NewsletterPrivacy($newsletter->subscribers, $newsletter->lists, $newsletter->deliveries, $newsletter->engagement, $newsletter->subscriptions);
        $news = $newsletter->list('news', false);
        $shop = $newsletter->list('news', false, 'shop');
        $newsletter->subscriptions->subscribe($news, 'ada@example.com', 'u1');
        $newsletter->subscriptions->subscribe($shop, 'ada@example.com', 'u1');
        $newsletter->subscriptions->subscribe($news, 'bob@example.com', 'u2');

        $ada = $newsletter->subscribers->byEmail(Site::default(), 'ada@example.com');
        self::assertNotNull($ada);
        $delivery = new Delivery('d1', $ada->id, 'c1', null, $newsletter->clock->now());
        $delivery->sent($newsletter->clock->now());

        $newsletter->deliveries->create($delivery);
        $newsletter->engagement->recordRecipientClick('c1', 3, $ada->id, $newsletter->clock->now());
        $newsletter->subscribers->saveMembership(new Membership($ada->id, 'gone', MembershipStatus::Unsubscribed, $newsletter->clock->now()));

        self::assertSame('newsletter', $privacy->module()->value);
        $export = $privacy->export(DataSubject::fromString('u1'));
        $subscriptions = $export['subscriptions'];
        self::assertIsArray($subscriptions);
        self::assertCount(2, $subscriptions);
        self::assertIsArray($subscriptions[1]);
        self::assertSame([
            'site' => 'default',
            'email' => 'ada@example.com',
            'status' => 'active',
            'subscribed_at' => '2026-11-13T10:00:00+00:00',
            'confirmed_at' => '2026-11-13T10:00:00+00:00',
            'paused_until' => null,
            'lists' => [
                ['list' => 'gone', 'status' => 'unsubscribed', 'since' => '2026-11-13T10:00:00+00:00'],
                ['list' => 'The news', 'status' => 'active', 'since' => '2026-11-13T10:00:00+00:00'],
            ],
            'messages' => [['campaign' => 'c1', 'template' => null, 'status' => 'sent', 'sent_at' => '2026-11-13T10:00:00+00:00']],
            'clicks' => [['campaign' => 'c1', 'link' => 3, 'at' => '2026-11-13T10:00:00+00:00']],
        ], $subscriptions[0]);
        self::assertSame('shop', $subscriptions[1]['site']);
        self::assertSame([], $privacy->export(DataSubject::fromString('nobody')));

        $report = $privacy->erase(DataSubject::fromString('u1'));
        self::assertSame('newsletter', $report->module->value);
        self::assertSame(ErasureEffect::Pseudonymised, $report->effects['suppressions']);
        self::assertSame(ErasureEffect::Deleted, $report->effects['subscribers']);
        self::assertSame([], $privacy->export(DataSubject::fromString('u1')));
        self::assertNotSame([], $privacy->export(DataSubject::fromString('u2')));
        self::assertEquals($report, $privacy->erase(DataSubject::fromString('u1')));
    }

    public function testWhatIsNotYetConfirmedNorSentIsExportedAsNothing(): void
    {
        $newsletter = new Newsletter();
        $privacy = new NewsletterPrivacy($newsletter->subscribers, $newsletter->lists, $newsletter->deliveries, $newsletter->engagement, $newsletter->subscriptions);
        $newsletter->subscriptions->subscribe($newsletter->list('news', true), 'ada@example.com', 'u1');
        $ada = $newsletter->subscribers->byEmail(Site::default(), 'ada@example.com');
        self::assertNotNull($ada);
        $newsletter->deliveries->create(new Delivery('d1', $ada->id, 'c1', null, $newsletter->clock->now()));

        $subscriptions = $privacy->export(DataSubject::fromString('u1'))['subscriptions'];

        self::assertIsArray($subscriptions);
        self::assertIsArray($subscriptions[0]);
        self::assertNull($subscriptions[0]['confirmed_at']);
        self::assertIsArray($subscriptions[0]['messages']);
        self::assertSame([['campaign' => 'c1', 'template' => null, 'status' => 'queued', 'sent_at' => null]], $subscriptions[0]['messages']);
    }
}
