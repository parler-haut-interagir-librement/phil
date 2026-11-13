<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Tests\Unit;

use DateTimeImmutable;
use Phil\AccessLinks\Contract\InvitationStore;
use Phil\AccessLinks\Invitation;
use PHPUnit\Framework\TestCase;

/**
 * Every promise of a store, run against each adapter.
 */
abstract class InvitationStoreTestCase extends TestCase
{
    abstract protected function store(): InvitationStore;

    public function testAnInvitationIsKeptAndFoundByItsLink(): void
    {
        $store = $this->store();
        $invitation = self::invitation('a', 'member-1', validFrom: 1, expiresAt: 20_000, nextReminderAt: 100);

        self::assertTrue($store->add($invitation, self::hash(1)));

        $kept = $store->get(self::id('a'));
        self::assertNotNull($kept);
        self::assertSame(self::id('a'), $kept->id);
        self::assertSame('poll-1', $kept->campaign);
        self::assertSame('member-1', $kept->recipient);
        self::assertSame(self::at(0)->getTimestamp(), $kept->createdAt->getTimestamp());
        self::assertSame(self::at(1)->getTimestamp(), $kept->validFrom->getTimestamp());
        self::assertSame(self::at(10_000)->getTimestamp(), $kept->closesAt->getTimestamp());
        self::assertSame(self::at(20_000)->getTimestamp(), $kept->expiresAt->getTimestamp());
        self::assertSame(self::at(100)->getTimestamp(), $kept->nextReminderAt?->getTimestamp());
        self::assertNull($kept->answer);
        self::assertNull($kept->respondedAt);
        self::assertNull($kept->answeredAt);
        self::assertNull($kept->revokedAt);
        self::assertSame(0, $kept->remindersSent);

        self::assertSame(self::id('a'), $store->findByToken([self::hash(9), self::hash(1)])?->id);
        self::assertNull($store->findByToken([self::hash(9)]));
        self::assertNull($store->get(self::id('b')));
    }

    public function testARecipientIsInvitedOncePerCampaign(): void
    {
        $store = $this->store();

        self::assertTrue($store->add(self::invitation('a', 'member-1'), self::hash(1)));
        self::assertFalse($store->add(self::invitation('b', 'member-1'), self::hash(2)));
        self::assertNull($store->get(self::id('b')));
        self::assertNull($store->findByToken([self::hash(2)]));
        self::assertTrue($store->add(self::invitation('c', 'member-1', 'poll-2'), self::hash(3)));
        self::assertTrue($store->add(self::invitation('d', 'member-2'), self::hash(4)));
    }

    public function testLinksAreAddedUpToTheMost(): void
    {
        $store = $this->store();
        $store->add(self::invitation('a', 'member-1'), self::hash(1));

        self::assertTrue($store->addLink(self::id('a'), self::hash(2), 3));
        self::assertTrue($store->addLink(self::id('a'), self::hash(3), 3));
        self::assertFalse($store->addLink(self::id('a'), self::hash(4), 3));
        self::assertFalse($store->addLink(self::id('b'), self::hash(5), 3));

        self::assertSame(self::id('a'), $store->findByToken([self::hash(1)])?->id);
        self::assertSame(self::id('a'), $store->findByToken([self::hash(3)])?->id);
        self::assertNull($store->findByToken([self::hash(4)]));
        self::assertNull($store->findByToken([self::hash(5)]));
    }

    public function testAnAnswerIsRecordedAndChangedUntilTheClosure(): void
    {
        $store = $this->store();
        $store->add(self::invitation('a', 'member-1', nextReminderAt: 100), self::hash(1));

        self::assertTrue($store->respond(self::id('a'), 'yes', self::at(50)));
        self::assertTrue($store->respond(self::id('a'), 'no', self::at(60)));

        $kept = $store->get(self::id('a'));
        self::assertNotNull($kept);
        self::assertSame('no', $kept->answer);
        self::assertSame(self::at(50)->getTimestamp(), $kept->respondedAt?->getTimestamp());
        self::assertSame(self::at(60)->getTimestamp(), $kept->answeredAt?->getTimestamp());
        self::assertNull($kept->nextReminderAt);

        self::assertTrue($store->respond(self::id('a'), 'maybe', self::at(9_999)));
        self::assertFalse($store->respond(self::id('a'), 'yes', self::at(10_000)));
        self::assertSame('maybe', $store->get(self::id('a'))?->answer);
        self::assertFalse($store->respond(self::id('b'), 'yes', self::at(50)));
    }

    public function testNoAnswerBeforeTheLinkOpensNorAfterRevocation(): void
    {
        $store = $this->store();
        $store->add(self::invitation('a', 'member-1', validFrom: 100), self::hash(1));
        $store->add(self::invitation('b', 'member-2', nextReminderAt: 500), self::hash(2));

        self::assertFalse($store->respond(self::id('a'), 'yes', self::at(99)));
        self::assertTrue($store->respond(self::id('a'), 'yes', self::at(100)));

        self::assertTrue($store->revoke(self::id('b'), self::at(10)));
        self::assertFalse($store->revoke(self::id('b'), self::at(20)));
        self::assertFalse($store->revoke(self::id('c'), self::at(20)));
        self::assertSame(self::at(10)->getTimestamp(), $store->get(self::id('b'))?->revokedAt?->getTimestamp());
        self::assertFalse($store->respond(self::id('b'), 'yes', self::at(30)));
        self::assertSame(self::at(500)->getTimestamp(), $store->get(self::id('b'))?->nextReminderAt?->getTimestamp());
    }

    public function testDueRemindersAreTheOverdueOnesWithoutAnswer(): void
    {
        $store = $this->store();
        $store->add(self::invitation('a', 'member-1', nextReminderAt: 300), self::hash(1));
        $store->add(self::invitation('b', 'member-2', nextReminderAt: 100), self::hash(2));
        $store->add(self::invitation('c', 'member-3', nextReminderAt: 200), self::hash(3));
        $store->add(self::invitation('d', 'member-4', nextReminderAt: 100), self::hash(4));
        $store->add(self::invitation('e', 'member-5', nextReminderAt: 100), self::hash(5));
        $store->add(self::invitation('f', 'member-6'), self::hash(6));
        $store->add(self::invitation('1', 'member-7', nextReminderAt: 400), self::hash(7));
        $store->respond(self::id('d'), 'yes', self::at(50));
        $store->revoke(self::id('e'), self::at(50));

        self::assertSame([self::id('b'), self::id('c'), self::id('a')], $store->due(self::at(300), 10));
        self::assertSame([self::id('b')], $store->due(self::at(300), 1));
        self::assertSame([], $store->due(self::at(99), 10));
        self::assertSame([], $store->due(self::at(10_000), 10));
    }

    public function testAReminderIsClaimedOnce(): void
    {
        $store = $this->store();
        $store->add(self::invitation('a', 'member-1', nextReminderAt: 100), self::hash(1));

        self::assertFalse($store->claimReminder(self::id('a'), 0, self::at(200), self::at(99)));
        self::assertTrue($store->claimReminder(self::id('a'), 0, self::at(200), self::at(100)));
        self::assertFalse($store->claimReminder(self::id('a'), 0, self::at(200), self::at(100)));

        $kept = $store->get(self::id('a'));
        self::assertNotNull($kept);
        self::assertSame(1, $kept->remindersSent);
        self::assertSame(self::at(200)->getTimestamp(), $kept->nextReminderAt?->getTimestamp());

        self::assertTrue($store->claimReminder(self::id('a'), 1, null, self::at(250)));
        self::assertNull($store->get(self::id('a'))?->nextReminderAt);
        self::assertSame(2, $store->get(self::id('a'))?->remindersSent);
        self::assertFalse($store->claimReminder(self::id('a'), 2, null, self::at(300)));
        self::assertFalse($store->claimReminder(self::id('b'), 0, null, self::at(300)));
    }

    public function testNoReminderIsClaimedAfterAnAnswerARevocationOrTheClosure(): void
    {
        $store = $this->store();
        $store->add(self::invitation('a', 'member-1', nextReminderAt: 100), self::hash(1));
        $store->add(self::invitation('b', 'member-2', nextReminderAt: 100), self::hash(2));
        $store->add(self::invitation('c', 'member-3', nextReminderAt: 100), self::hash(3));
        $store->respond(self::id('a'), 'yes', self::at(50));
        $store->revoke(self::id('b'), self::at(50));

        self::assertFalse($store->claimReminder(self::id('a'), 0, null, self::at(100)));
        self::assertFalse($store->claimReminder(self::id('b'), 0, null, self::at(100)));
        self::assertFalse($store->claimReminder(self::id('c'), 0, null, self::at(10_000)));
        self::assertTrue($store->claimReminder(self::id('c'), 0, null, self::at(9_999)));
    }

    public function testACampaignIsCounted(): void
    {
        $store = $this->store();
        $store->add(self::invitation('e', 'member-5', 'poll-2'), self::hash(5));
        $store->add(self::invitation('a', 'member-1'), self::hash(1));
        $store->add(self::invitation('b', 'member-2'), self::hash(2));
        $store->add(self::invitation('c', 'member-3'), self::hash(3));
        $store->add(self::invitation('d', 'member-4'), self::hash(4));
        $store->add(self::invitation('f', 'member-6'), self::hash(6));
        $store->add(self::invitation('1', 'member-7'), self::hash(7));
        $store->add(self::invitation('2', 'member-8'), self::hash(8));
        $store->respond(self::id('a'), 'yes', self::at(10));
        $store->respond(self::id('b'), 'yes', self::at(10));
        $store->revoke(self::id('b'), self::at(20));
        $store->revoke(self::id('c'), self::at(20));
        $store->revoke(self::id('f'), self::at(20));
        $store->revoke(self::id('1'), self::at(20));

        $counts = $store->counts('poll-1');
        self::assertSame([7, 2, 3, 2], [$counts->invited, $counts->responded, $counts->revoked, $counts->pending()]);
        self::assertSame([1, 0, 0, 1], [$store->counts('poll-2')->invited, $store->counts('poll-2')->responded, $store->counts('poll-2')->revoked, $store->counts('poll-2')->pending()]);
        self::assertSame(0, $store->counts('poll-9')->invited);
        self::assertSame(0, $store->counts('poll-9')->revoked);
    }

    protected static function at(int $seconds): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-13 10:00:00 UTC')->modify(\sprintf('+%d seconds', $seconds));
    }

    protected static function id(string $letter): string
    {
        return str_repeat($letter, 32);
    }

    /**
     * @return non-empty-string
     */
    protected static function hash(int $n): string
    {
        return '$hmac-sha256$v=1$'.hash('sha256', (string) $n);
    }

    protected static function invitation(string $letter, string $recipient, string $campaign = 'poll-1', ?int $validFrom = null, ?int $expiresAt = null, ?int $nextReminderAt = null): Invitation
    {
        return new Invitation(
            self::id($letter),
            $campaign,
            $recipient,
            self::at(0),
            self::at(10_000),
            null === $expiresAt ? null : self::at($expiresAt),
            null === $validFrom ? null : self::at($validFrom),
            nextReminderAt: null === $nextReminderAt ? null : self::at($nextReminderAt),
        );
    }
}
