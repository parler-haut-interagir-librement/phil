<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\NewsletterBundle\Domain\Enum\CampaignStatus;
use Phil\NewsletterBundle\Domain\Enum\ClickTracking;
use Phil\NewsletterBundle\Domain\Enum\DeliveryStatus;
use Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue;
use Phil\NewsletterBundle\Domain\Model\Campaign;
use Phil\NewsletterBundle\Domain\Model\Delivery;
use Phil\NewsletterBundle\Domain\Model\MailingList;
use Phil\NewsletterBundle\Domain\Model\Template;
use Phil\NewsletterBundle\Domain\ValueObject\Site;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Campaign::class)]
#[CoversClass(Delivery::class)]
#[CoversClass(MailingList::class)]
#[CoversClass(Template::class)]
final class CampaignTest extends TestCase
{
    public function testACampaignIsEditedThenScheduledThenSentOnce(): void
    {
        $at = new DateTimeImmutable('@1800000000');
        $campaign = new Campaign('c', Site::default(), 'l', 'Hello', 'Body');

        self::assertSame(CampaignStatus::Draft, $campaign->status);
        self::assertSame(ClickTracking::Aggregate, $campaign->clickTracking);
        self::assertSame('', $campaign->preheader);
        $campaign->edit('New', 'Text', 'Pre', 'vip', 'layout', 'promo', ClickTracking::PerRecipient);
        self::assertSame(['New', 'Text', 'Pre', 'vip', 'layout', 'promo', ClickTracking::PerRecipient], [$campaign->subject, $campaign->body, $campaign->preheader, $campaign->segment, $campaign->template, $campaign->senderProfile, $campaign->clickTracking]);

        $campaign->schedule($at);
        $campaign->schedule($at->modify('+1 hour'));
        self::assertSame(CampaignStatus::Scheduled, $campaign->status);
        self::assertSame(1_800_003_600, $campaign->scheduledAt?->getTimestamp());
        $campaign->start(false);
        self::assertSame(CampaignStatus::Sending, $campaign->status);
        $campaign->finish($at);
        self::assertSame(CampaignStatus::Sent, $campaign->status);
        self::assertSame($at, $campaign->sentAt);

        foreach ([
            'A campaign that is sent cannot be sent.' => $campaign->start(...),
            'A campaign that is sent cannot be cancelled.' => $campaign->cancel(...),
            'A campaign that is sent cannot be finished.' => static fn () => $campaign->finish($at),
            'A campaign that is sent cannot be scheduled.' => static fn () => $campaign->schedule($at),
            'Only a draft campaign is edited.' => static fn () => $campaign->edit('A', 'B', '', null, null, null, ClickTracking::Off),
        ] as $message => $change) {
            try {
                $change();
                self::fail('Expected a refusal.');
            } catch (InvalidNewsletterValue $refused) {
                self::assertSame($message, $refused->getMessage());
            }
        }

        $draft = new Campaign('d', Site::default(), 'l', 'S', 'B');
        $draft->cancel();
        self::assertSame(CampaignStatus::Cancelled, $draft->status);
        $restored = Campaign::restore(new Campaign('e', Site::default(), 'l', 'S', 'B'), CampaignStatus::Sent, $at, $at);
        self::assertSame([CampaignStatus::Sent, $at, $at], [$restored->status, $restored->scheduledAt, $restored->sentAt]);
    }

    public function testASubjectIsShortAndPlain(): void
    {
        foreach (['', '  ', str_repeat('é', 201), "a\rb"] as $subject) {
            try {
                new Campaign('c', Site::default(), 'l', $subject, 'B');
                self::fail('Expected a refusal.');
            } catch (InvalidNewsletterValue $refused) {
                self::assertSame('The subject of a campaign is not blank, holds at most 200 characters and no control character.', $refused->getMessage());
            }
        }

        self::assertSame(200, mb_strlen(new Campaign('c', Site::default(), 'l', str_repeat('é', 200), 'B')->subject));
    }

    public function testDeliveries(): void
    {
        $at = new DateTimeImmutable('@1800000000');
        $delivery = new Delivery(Delivery::idFor('c', 's'), 's', 'c', null, $at);

        self::assertSame(32, \strlen($delivery->id));
        self::assertSame(Delivery::idFor('c', 's'), $delivery->id);
        self::assertNotSame(Delivery::idFor('c', 's'), Delivery::idFor('c', 't'));
        self::assertNotSame(Delivery::idFor('c', 's'), Delivery::idForKey('c'."\0".'s'));
        self::assertSame(32, \strlen(Delivery::idForKey('k')));
        self::assertTrue($delivery->isPending());
        $delivery->failed();
        self::assertSame([DeliveryStatus::Failed, 1], [$delivery->status, $delivery->attempts]);
        self::assertTrue($delivery->isPending());
        $delivery->sent($at);
        self::assertSame([DeliveryStatus::Sent, 2, $at], [$delivery->status, $delivery->attempts, $delivery->sentAt]);
        self::assertFalse($delivery->isPending());
        $delivery->skipped();
        self::assertSame(DeliveryStatus::Skipped, $delivery->status);
        self::assertFalse($delivery->isPending());
        $delivery->bounced();
        self::assertSame(DeliveryStatus::Bounced, $delivery->status);
        $restored = Delivery::restore(new Delivery('x', 's', null, 't', $at), DeliveryStatus::Queued, 3, null);
        self::assertSame([3, 't', null], [$restored->attempts, $restored->template, $restored->campaignId]);
    }

    public function testListsAndTemplates(): void
    {
        $at = new DateTimeImmutable('@1800000000');
        $list = new MailingList('l', Site::default(), 'news-1', ' News ', true, 'default', $at);
        self::assertSame('News', $list->name);
        $list->change('Other', false, 'promo');
        self::assertSame(['Other', false, 'promo'], [$list->name, $list->doubleOptIn, $list->senderProfile]);

        $template = new Template('welcome', null, 'Hi', '<p>{{ site }}</p>', $at);
        self::assertTrue($template->isShared());
        self::assertFalse(new Template('welcome', Site::default(), 'Hi', '', $at)->isShared());
        $template->change('Hello', 'x', $at->modify('+1 day'));
        self::assertSame(['Hello', 'x', 1_800_086_400], [$template->subject, $template->source, $template->updatedAt->getTimestamp()]);

        foreach ([
            '"News" is not a list key' => static fn (): MailingList => new MailingList('l', Site::default(), 'News', 'N', true, 'd', $at),
            'The name of a list is not blank and holds at most 120 characters.' => static fn () => $list->change(str_repeat('a', 121), true, 'd'),
            '"Welcome" is not a template key' => static fn (): Template => new Template('Welcome', null, '', '', $at),
            'A template has a subject of at most 200 characters' => static fn (): Template => new Template('w', null, str_repeat('a', 201), '', $at),
            'and a source of at most 65536 bytes of UTF-8.' => static fn () => $template->change('', str_repeat('a', 65_537), $at),
        ] as $message => $build) {
            try {
                $build();
                self::fail('Expected a refusal.');
            } catch (InvalidNewsletterValue $refused) {
                self::assertStringContainsString($message, $refused->getMessage());
            }
        }

        self::assertSame(65_536, \strlen(new Template('w', null, '', str_repeat('a', 65_536), $at)->source));
        $this->expectException(InvalidNewsletterValue::class);
        new Template('w', null, '', "\xFF", $at);
    }
}
