<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\NewsletterBundle\Domain\Enum\CampaignStatus;
use Phil\NewsletterBundle\Domain\Enum\ClickTracking;
use Phil\NewsletterBundle\Domain\Enum\DeliveryStatus;
use Phil\NewsletterBundle\Domain\Enum\MembershipStatus;
use Phil\NewsletterBundle\Domain\Enum\PauseReason;
use Phil\NewsletterBundle\Domain\Enum\SendRunCounter;
use Phil\NewsletterBundle\Domain\Enum\SendRunStatus;
use Phil\NewsletterBundle\Domain\Enum\SubscriberStatus;
use Phil\NewsletterBundle\Domain\Enum\UnsubscribeReason;
use Phil\NewsletterBundle\Domain\Model\Campaign;
use Phil\NewsletterBundle\Domain\Model\Delivery;
use Phil\NewsletterBundle\Domain\Model\MailingList;
use Phil\NewsletterBundle\Domain\Model\Membership;
use Phil\NewsletterBundle\Domain\Model\SendRun;
use Phil\NewsletterBundle\Domain\Model\Subscriber;
use Phil\NewsletterBundle\Domain\Model\Template;
use Phil\NewsletterBundle\Domain\ValueObject\Site;
use Phil\NewsletterBundle\Tests\Unit\Fixture\Newsletter;
use PHPUnit\Framework\TestCase;

/**
 * Every promise of the DBAL repositories, on the server a subclass gives.
 */
abstract class RepositoriesTestCase extends TestCase
{
    protected Newsletter $newsletter;

    abstract protected function newsletter(): Newsletter;

    protected function setUp(): void
    {
        $this->newsletter = $this->newsletter();
    }

    public function testLists(): void
    {
        $at = new DateTimeImmutable('@1800000000');
        $news = new MailingList('l1', Site::default(), 'news', 'News', true, 'default', $at);
        $this->newsletter->lists->save($news);
        $this->newsletter->lists->save(new MailingList('l2', Site::default(), 'events', 'Events', false, 'promo', $at));
        $this->newsletter->lists->save(new MailingList('l3', Site::of('shop'), 'news', 'Shop news', false, 'default', $at));

        $news->change('Weekly news', false, 'promo');
        $this->newsletter->lists->save($news);

        self::assertEquals($news, $this->newsletter->lists->get('l1'));
        self::assertNull($this->newsletter->lists->get('none'));
        self::assertSame('l3', $this->newsletter->lists->byKey(Site::of('shop'), 'news')?->id);
        self::assertNull($this->newsletter->lists->byKey(Site::of('other'), 'news'));
        self::assertSame(['events', 'news'], array_map(static fn (MailingList $list): string => $list->key, $this->newsletter->lists->ofSite(Site::default())));
    }

    public function testSubscribersAndMemberships(): void
    {
        $at = new DateTimeImmutable('@1800000000');
        $ada = Subscriber::restore('s1', Site::default(), 'Ada@Example.com', 'u1', SubscriberStatus::Paused, $at, $at, $at->modify('+1 day'), 2);
        $this->newsletter->subscribers->save($ada);
        $ada->resume();
        $this->newsletter->subscribers->save($ada);
        $this->newsletter->subscribers->save(new Subscriber('s2', Site::of('shop'), 'ada@example.com', 'u1', SubscriberStatus::Active, $at));

        self::assertEquals($ada, $this->newsletter->subscribers->get('s1'));
        self::assertSame('s1', $this->newsletter->subscribers->byEmail(Site::default(), 'ada@example.com')?->id);
        self::assertSame('s2', $this->newsletter->subscribers->byExternalId(Site::of('shop'), 'u1')?->id);
        self::assertNull($this->newsletter->subscribers->byExternalId(Site::of('shop'), 'u2'));
        self::assertSame(['s1', 's2'], array_map(static fn (Subscriber $subscriber): string => $subscriber->id, $this->newsletter->subscribers->withExternalId('u1')));

        $membership = new Membership('s1', 'l1', MembershipStatus::Pending, $at);
        $this->newsletter->subscribers->saveMembership($membership);
        $membership->activate($at->modify('+1 hour'));
        $this->newsletter->subscribers->saveMembership($membership);
        $this->newsletter->subscribers->saveMembership(new Membership('s1', 'l0', MembershipStatus::Unsubscribed, $at));
        self::assertEquals($membership, $this->newsletter->subscribers->membership('s1', 'l1'));
        self::assertNull($this->newsletter->subscribers->membership('s1', 'l9'));
        self::assertSame(['l0', 'l1'], array_map(static fn (Membership $membership): string => $membership->listId, $this->newsletter->subscribers->memberships('s1')));

        $this->newsletter->subscribers->remove('s1');
        self::assertNull($this->newsletter->subscribers->get('s1'));
        self::assertSame([], $this->newsletter->subscribers->memberships('s1'));
        self::assertNotNull($this->newsletter->subscribers->get('s2'));
    }

    public function testRecipientsArePagedAndFiltered(): void
    {
        $at = new DateTimeImmutable('@1800000000');
        $statuses = ['a' => SubscriberStatus::Active, 'b' => SubscriberStatus::Paused, 'c' => SubscriberStatus::Paused, 'd' => SubscriberStatus::Unsubscribed, 'e' => SubscriberStatus::Bounced, 'f' => SubscriberStatus::Active, 'g' => SubscriberStatus::Active];

        foreach ($statuses as $id => $status) {
            $pausedUntil = 'b' === $id ? $at : ('c' === $id ? $at->modify('+1 day') : null);
            $this->newsletter->subscribers->save(Subscriber::restore($id, Site::default(), $id.'@example.com', null, $status, $at, null, $pausedUntil, 0));
            $this->newsletter->subscribers->saveMembership(new Membership($id, 'g' === $id ? 'other' : 'l1', 'f' === $id ? MembershipStatus::Unsubscribed : MembershipStatus::Active, $at));
        }

        self::assertSame(['a', 'b'], $this->newsletter->subscribers->recipients('l1', $at, null, 10));
        self::assertSame(['a'], $this->newsletter->subscribers->recipients('l1', $at, null, 1));
        self::assertSame(['b'], $this->newsletter->subscribers->recipients('l1', $at, 'a', 0));
        self::assertSame(['a'], $this->newsletter->subscribers->recipients('l1', $at->modify('-1 second'), null, 10));
    }

    public function testCampaignsAndLinks(): void
    {
        $at = new DateTimeImmutable('@1800000000');
        $campaign = new Campaign('c1', Site::default(), 'l1', 'Subject', 'Body', 'Pre', 'vip', 'house', 'promo', ClickTracking::PerRecipient);
        $this->newsletter->campaigns->save($campaign);
        self::assertEquals($campaign, $this->newsletter->campaigns->get('c1'));
        $campaign->schedule($at);
        $this->newsletter->campaigns->save($campaign);
        $later = new Campaign('c2', Site::default(), 'l1', 'S', 'B');
        $later->schedule($at->modify('+1 hour'));

        $this->newsletter->campaigns->save($later);
        $sent = Campaign::restore(new Campaign('c3', Site::default(), 'l1', 'S', 'B'), CampaignStatus::Sent, $at, $at);
        $this->newsletter->campaigns->save($sent);

        self::assertEquals($campaign, $this->newsletter->campaigns->get('c1'));
        self::assertEquals($sent, $this->newsletter->campaigns->get('c3'));
        self::assertNull($this->newsletter->campaigns->get('none'));
        self::assertSame(['c1'], array_map(static fn (Campaign $campaign): string => $campaign->id, $this->newsletter->campaigns->due($at)));
        self::assertSame(['c1', 'c2'], array_map(static fn (Campaign $campaign): string => $campaign->id, $this->newsletter->campaigns->due($at->modify('+1 hour'))));

        self::assertSame(0, $this->newsletter->campaigns->linkIndex('c1', 'https://example.com/a'));
        self::assertSame(1, $this->newsletter->campaigns->linkIndex('c1', 'https://example.com/b'));
        self::assertSame(0, $this->newsletter->campaigns->linkIndex('c1', 'https://example.com/a'));
        self::assertSame(0, $this->newsletter->campaigns->linkIndex('c2', 'https://example.com/b'));
        self::assertSame('https://example.com/b', $this->newsletter->campaigns->link('c1', 1));
        self::assertNull($this->newsletter->campaigns->link('c1', 2));
    }

    public function testDeliveries(): void
    {
        $at = new DateTimeImmutable('@1800000000');
        $delivery = new Delivery('d1', 's1', 'c1', null, $at);
        self::assertTrue($this->newsletter->deliveries->create($delivery));
        self::assertFalse($this->newsletter->deliveries->create(new Delivery('d1', 's9', null, 't', $at)));
        $delivery->sent($at);
        $this->newsletter->deliveries->save($delivery);
        $this->newsletter->deliveries->create(new Delivery('d2', 's1', 'c1', null, $at->modify('+1 second')));
        $this->newsletter->deliveries->create(new Delivery('d3', 's2', null, 'welcome', $at));

        self::assertEquals($delivery, $this->newsletter->deliveries->get('d1'));
        self::assertNull($this->newsletter->deliveries->get('none'));
        self::assertSame(['d1', 'd2'], array_map(static fn (Delivery $delivery): string => $delivery->id, $this->newsletter->deliveries->ofSubscriber('s1')));
        self::assertSame([DeliveryStatus::Queued->value => 1, DeliveryStatus::Sent->value => 1], $this->newsletter->deliveries->countByStatus('c1'));
        self::assertSame(2, $this->newsletter->deliveries->removeOfSubscriber('s1'));
        self::assertSame([], $this->newsletter->deliveries->ofSubscriber('s1'));
        self::assertSame('welcome', $this->newsletter->deliveries->get('d3')?->template);
    }

    public function testTheAuthorAndTheApprovalOfACampaign(): void
    {
        $at = new DateTimeImmutable('@1800000000');
        $campaign = new Campaign('c1', Site::default(), 'l1', 'Subject', 'Body', author: 'ann@example.com');
        $this->newsletter->campaigns->save($campaign);
        self::assertEquals($campaign, $this->newsletter->campaigns->get('c1'));

        $campaign->approve('bob@example.com', $at, true);
        $this->newsletter->campaigns->save($campaign);
        $stored = $this->newsletter->campaigns->get('c1');
        self::assertEquals($campaign, $stored);
        self::assertSame(['ann@example.com', 'bob@example.com', 1_800_000_000], [$stored?->author, $stored?->approval?->approver, $stored?->approval?->approvedAt->getTimestamp()]);

        $campaign->edit('Subject', 'Another body', '', null, null, null, ClickTracking::Aggregate);
        $this->newsletter->campaigns->save($campaign);
        self::assertNull($this->newsletter->campaigns->get('c1')?->approval);
    }

    public function testSendRuns(): void
    {
        $at = new DateTimeImmutable('@1800000000');
        $first = new SendRun(SendRun::idFor('c1', 1), 'c1', 1, $at);
        self::assertTrue($this->newsletter->runs->create($first));
        self::assertFalse($this->newsletter->runs->create(new SendRun(SendRun::idFor('c1', 1), 'c9', 1, $at->modify('+1 day'))));
        self::assertTrue($this->newsletter->runs->create(new SendRun(SendRun::idFor('c1', 2), 'c1', 2, $at)));
        $this->newsletter->runs->create(new SendRun(SendRun::idFor('c2', 1), 'c2', 1, $at));
        self::assertEquals($first, $this->newsletter->runs->get($first->id));
        self::assertNull($this->newsletter->runs->get('none'));
        self::assertSame([1, 2], array_map(static fn (SendRun $run): int => $run->number, $this->newsletter->runs->ofCampaign('c1')));

        foreach ([SendRunCounter::Sent, SendRunCounter::Sent, SendRunCounter::Failed, SendRunCounter::Skipped, SendRunCounter::Bounced, SendRunCounter::Complained] as $counter) {
            $this->newsletter->runs->add($first->id, $counter);
        }

        $this->newsletter->runs->add($first->id, SendRunCounter::Skipped, 3);
        $first->queuedAll(9, $at->modify('+1 minute'));
        $this->newsletter->runs->queuedAll($first);
        $stored = $this->newsletter->runs->get($first->id);
        self::assertInstanceOf(SendRun::class, $stored);
        self::assertSame([9, 60, 2, 1, 4, 1, 1], [$stored->queued, ($stored->queuedAt?->getTimestamp() ?? 0) - $at->getTimestamp(), $stored->count(SendRunCounter::Sent), $stored->count(SendRunCounter::Failed), $stored->count(SendRunCounter::Skipped), $stored->count(SendRunCounter::Bounced), $stored->count(SendRunCounter::Complained)]);
        self::assertSame(0, $this->newsletter->runs->get(SendRun::idFor('c1', 2))?->count(SendRunCounter::Sent));

        $stored->pause(PauseReason::Bounces, $at->modify('+2 minutes'));
        self::assertTrue($this->newsletter->runs->transition($stored, SendRunStatus::Running));
        self::assertFalse($this->newsletter->runs->transition($stored, SendRunStatus::Running));
        $paused = $this->newsletter->runs->get($first->id);
        self::assertSame([SendRunStatus::Paused, PauseReason::Bounces, 120], [$paused?->status, $paused?->pauseReason, ($paused?->pausedAt?->getTimestamp() ?? 0) - $at->getTimestamp()]);

        $stored->resume();
        self::assertTrue($this->newsletter->runs->transition($stored, SendRunStatus::Paused));
        $resumed = $this->newsletter->runs->get($first->id);
        self::assertInstanceOf(SendRun::class, $resumed);
        self::assertEquals($stored, $resumed);
        self::assertSame([SendRunCounter::Sent->value => 2, SendRunCounter::Failed->value => 1, SendRunCounter::Bounced->value => 1, SendRunCounter::Complained->value => 1], $resumed->baseline());
        self::assertSame([null, null, 0], [$resumed->pauseReason, $resumed->pausedAt, $resumed->sinceResume(SendRunCounter::Sent)]);

        $stored->cancel($at->modify('+3 minutes'));
        self::assertTrue($this->newsletter->runs->transition($stored, SendRunStatus::Running));
        self::assertSame(180, ($this->newsletter->runs->get($first->id)?->endedAt?->getTimestamp() ?? 0) - $at->getTimestamp());
    }

    public function testTheDeliveriesOfASendRun(): void
    {
        $at = new DateTimeImmutable('@1800000000');

        foreach (['d3', 'd1', 'd4', 'd2', 'd5'] as $id) {
            $this->newsletter->deliveries->create(new Delivery($id, 's-'.$id, 'c1', null, $at, 'r1'));
        }

        $this->newsletter->deliveries->create(new Delivery('d0', 's0', 'c1', null, $at, 'r2'));
        $sent = new Delivery('d5', 's-d5', 'c1', null, $at, 'r1');
        $sent->sent($at);

        $this->newsletter->deliveries->save($sent);
        $failed = new Delivery('d4', 's-d4', 'c1', null, $at, 'r1');
        $failed->failed();

        $this->newsletter->deliveries->save($failed);

        self::assertSame('r1', $this->newsletter->deliveries->get('d1')?->runId);
        self::assertSame(5, $this->newsletter->deliveries->countOfRun('r1'));
        self::assertSame(0, $this->newsletter->deliveries->countOfRun('none'));
        self::assertSame(['d1', 'd2'], $this->newsletter->deliveries->pendingOfRun('r1', null, 2));
        self::assertSame(['d3', 'd4'], $this->newsletter->deliveries->pendingOfRun('r1', 'd2', 2));
        self::assertSame([], $this->newsletter->deliveries->pendingOfRun('r1', 'd4', 2));
        self::assertSame(4, $this->newsletter->deliveries->skipPendingOfRun('r1'));
        self::assertSame([], $this->newsletter->deliveries->pendingOfRun('r1', null, 10));
        self::assertSame([DeliveryStatus::Queued, DeliveryStatus::Sent, DeliveryStatus::Skipped], [$this->newsletter->deliveries->get('d0')?->status, $this->newsletter->deliveries->get('d5')?->status, $this->newsletter->deliveries->get('d4')?->status]);
    }

    public function testTemplatesOfASiteComeBeforeSharedOnes(): void
    {
        $at = new DateTimeImmutable('@1800000000');
        $shared = new Template('welcome', null, 'Shared', 'shared', $at);
        $own = new Template('welcome', Site::of('shop'), 'Own', 'own', $at);
        $this->newsletter->templates->save($shared);
        $this->newsletter->templates->save($own);

        $own->change('Own 2', 'own 2', $at->modify('+1 day'));
        $this->newsletter->templates->save($own);

        self::assertEquals($own, $this->newsletter->templates->find(Site::of('shop'), 'welcome'));
        self::assertEquals($shared, $this->newsletter->templates->find(Site::default(), 'welcome'));
        self::assertNull($this->newsletter->templates->find(Site::default(), 'other'));
        $this->newsletter->templates->remove(Site::of('shop'), 'welcome');
        self::assertEquals($shared, $this->newsletter->templates->find(Site::of('shop'), 'welcome'));
        $this->newsletter->templates->remove(null, 'welcome');
        self::assertNull($this->newsletter->templates->find(Site::of('shop'), 'welcome'));
    }

    public function testEngagement(): void
    {
        $at = new DateTimeImmutable('2026-11-13 23:30:00 UTC');
        $this->newsletter->engagement->countClick('c1', 0);
        $this->newsletter->engagement->countClick('c1', 0);
        $this->newsletter->engagement->countClick('c1', 2);
        $this->newsletter->engagement->countClick('c2', 0);
        self::assertSame([0 => 2, 2 => 1], $this->newsletter->engagement->clicks('c1'));
        self::assertSame([], $this->newsletter->engagement->clicks('none'));

        $this->newsletter->engagement->recordRecipientClick('c1', 0, 's1', $at);
        $this->newsletter->engagement->recordRecipientClick('c1', 0, 's1', $at);
        $this->newsletter->engagement->recordRecipientClick('c1', 1, 's1', $at->modify('+1 second'));

        $clicks = $this->newsletter->engagement->recipientClicks('s1');
        self::assertSame([['c1', 0, $at->getTimestamp()], ['c1', 1, $at->getTimestamp() + 1]], array_map(static fn (array $click): array => [$click['campaign'], $click['link'], $click['at']->getTimestamp()], $clicks));
        self::assertSame(2, $this->newsletter->engagement->forgetRecipientClicks('s1'));
        self::assertSame([], $this->newsletter->engagement->recipientClicks('s1'));

        $this->newsletter->engagement->countUnsubscribeReason(Site::default(), 'l1', UnsubscribeReason::TooFrequent, $at);
        $this->newsletter->engagement->countUnsubscribeReason(Site::default(), 'l1', UnsubscribeReason::TooFrequent, $at->modify('+20 minutes'));
        $this->newsletter->engagement->countUnsubscribeReason(Site::default(), null, UnsubscribeReason::TooFrequent, $at);
        $this->newsletter->engagement->countUnsubscribeReason(Site::default(), null, UnsubscribeReason::Other, $at->modify('-2 days'));
        $this->newsletter->engagement->countUnsubscribeReason(Site::of('shop'), null, UnsubscribeReason::Other, $at);
        self::assertSame(['too_frequent' => 3], $this->newsletter->engagement->unsubscribeReasons(Site::default(), $at->modify('-20 minutes')));
        self::assertSame(['other' => 1, 'too_frequent' => 3], $this->newsletter->engagement->unsubscribeReasons(Site::default(), $at->modify('-2 days')));
        self::assertSame([], $this->newsletter->engagement->unsubscribeReasons(Site::default(), $at->modify('+2 days')));
    }

    public function testSuppressionsAreKeptAsKeyedHashes(): void
    {
        $this->newsletter->suppressions->suppress(Site::default(), 'ada@example.com', 'bounced');
        $this->newsletter->suppressions->suppress(Site::default(), 'ada@example.com', 'complained');

        self::assertTrue($this->newsletter->suppressions->isSuppressed(Site::default(), 'ada@example.com'));
        self::assertFalse($this->newsletter->suppressions->isSuppressed(Site::of('shop'), 'ada@example.com'));
        self::assertFalse($this->newsletter->suppressions->isSuppressed(Site::default(), 'bob@example.com'));
        $rows = $this->newsletter->connection->fetchAllAssociative('SELECT email_hash, reason FROM phil_newsletter_suppression');
        self::assertCount(1, $rows);
        self::assertSame(hash_hmac('sha256', "default\0ada@example.com", Newsletter::KEY), $rows[0]['email_hash']);
        self::assertSame('bounced', $rows[0]['reason']);
    }

    public function testAListKeepsItsDoubleOptInAsOneOrZero(): void
    {
        $at = new DateTimeImmutable('@1800000000');
        $this->newsletter->lists->save(new MailingList('l1', Site::default(), 'a', 'A', true, 'default', $at));
        $this->newsletter->lists->save(new MailingList('l2', Site::default(), 'b', 'B', false, 'default', $at));

        self::assertEquals(['l1' => 1, 'l2' => 0], $this->newsletter->connection->fetchAllKeyValue('SELECT id, double_opt_in FROM phil_newsletter_list'));
        self::assertTrue($this->newsletter->lists->get('l1')?->doubleOptIn);
        self::assertFalse($this->newsletter->lists->get('l2')?->doubleOptIn);
    }

    public function testTheDaysOfTheReasonsAreMidnightsOfUtc(): void
    {
        $at = new DateTimeImmutable('2026-11-13 23:30:45 UTC');

        $this->newsletter->engagement->countUnsubscribeReason(Site::default(), 'l1', UnsubscribeReason::TooFrequent, $at);
        $this->newsletter->engagement->countUnsubscribeReason(Site::default(), null, UnsubscribeReason::Other, new DateTimeImmutable('2026-11-14 01:30:45 America/Toronto'));

        self::assertEquals([
            ['l1', 'too_frequent', new DateTimeImmutable('2026-11-13 00:00:00 UTC')->getTimestamp()],
            ['', 'other', new DateTimeImmutable('2026-11-14 00:00:00 UTC')->getTimestamp()],
        ], array_map(static fn (array $row): array => [$row['list_id'], $row['reason'], $row['day']], $this->newsletter->connection->fetchAllAssociative('SELECT list_id, reason, day FROM phil_newsletter_unsubscribe_reason ORDER BY day')));
    }

    public function testForgettingOneSubscriberKeepsTheOthers(): void
    {
        $at = new DateTimeImmutable('@1800000000');

        foreach (['s1', 's2'] as $id) {
            $this->newsletter->subscribers->save(new Subscriber($id, Site::default(), $id.'@example.com', null, SubscriberStatus::Active, $at));
            $this->newsletter->subscribers->saveMembership(new Membership($id, 'l1', MembershipStatus::Active, $at));
            $this->newsletter->engagement->recordRecipientClick('c1', 0, $id, $at);
        }

        self::assertSame(1, $this->newsletter->engagement->forgetRecipientClicks('s1'));
        $this->newsletter->subscribers->remove('s1');

        self::assertCount(1, $this->newsletter->engagement->recipientClicks('s2'));
        self::assertNotNull($this->newsletter->subscribers->membership('s2', 'l1'));
        self::assertNotNull($this->newsletter->subscribers->get('s2'));
    }

    public function testAKnownLinkKeepsItsIndex(): void
    {
        self::assertSame(0, $this->newsletter->campaigns->linkIndex('c1', 'https://example.com/a'));
        self::assertSame(1, $this->newsletter->campaigns->linkIndex('c1', 'https://example.com/b'));
        self::assertSame(1, $this->newsletter->campaigns->linkIndex('c1', 'https://example.com/b'));
        self::assertSame(2, $this->newsletter->campaigns->linkIndex('c1', 'https://example.com/c'));
        self::assertEquals(3, $this->newsletter->connection->fetchOne('SELECT COUNT(*) FROM phil_newsletter_link WHERE campaign_id = ?', ['c1']));
    }

    public function testAReasonOfASuppressionIsKeptToSixteenCharacters(): void
    {
        $this->newsletter->suppressions->suppress(Site::default(), 'ada@example.com', 'abcdefghijklmnopqrstuvwxyz');

        self::assertSame('abcdefghijklmnop', $this->newsletter->connection->fetchOne('SELECT reason FROM phil_newsletter_suppression'));
    }
}
