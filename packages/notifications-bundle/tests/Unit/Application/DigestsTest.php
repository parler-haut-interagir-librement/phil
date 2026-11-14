<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\NotificationsBundle\Application\Digests;
use Phil\NotificationsBundle\Application\Handler\DigestHandler;
use Phil\NotificationsBundle\Application\Handler\PurgeNotificationsHandler;
use Phil\NotificationsBundle\Application\Message\SendDigest;
use Phil\NotificationsBundle\Application\Message\SendDigests;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\Preferences\DigestFrequency;
use Phil\NotificationsBundle\Application\Preferences\RecipientSettings;
use Phil\NotificationsBundle\Application\RenderedNotification;
use Phil\NotificationsBundle\Application\Retention;
use Phil\NotificationsBundle\Domain\Model\Notification;
use Phil\NotificationsBundle\Tests\Unit\Fixture\CountingPurges;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Directory;
use Phil\NotificationsBundle\Tests\Unit\Fixture\RecordingBus;
use Phil\NotificationsBundle\Tests\Unit\Fixture\RecordingDigests;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Recipient\Recipient;

#[CoversClass(Digests::class)]
#[CoversClass(DigestHandler::class)]
#[CoversClass(Retention::class)]
#[CoversClass(PurgeNotificationsHandler::class)]
final class DigestsTest extends TestCase
{
    private Kit $kit;

    private RecordingDigests $sender;

    protected function setUp(): void
    {
        $this->kit = new Kit(new NotificationType('comment.replied'));
        $this->sender = new RecordingDigests();
    }

    public function testADigestHoldsTheUnreadNotificationsOfTheWindowOldestFirst(): void
    {
        $this->kit->preferences->settings['42'] = new RecipientSettings(DigestFrequency::Daily, locale: 'fr');
        $this->keep('old', '42', '-25 hours');
        $this->keep('first', '42', '-23 hours');
        $this->keep('second', '42', '-1 hour');
        $this->keep('read', '42', '-2 hours')->markRead(new DateTimeImmutable());

        self::assertTrue($this->digests()->send('42', DigestFrequency::Daily));
        self::assertCount(1, $this->sender->sent);
        [$contact, $frequency, $notifications, $locale] = $this->sender->sent[0];
        self::assertSame('ada@example.test', $contact instanceof Recipient ? $contact->getEmail() : null);
        self::assertSame(DigestFrequency::Daily, $frequency);
        self::assertSame('fr', $locale);
        self::assertSame(['first', 'second'], array_map(static fn (RenderedNotification $n): string => $n->notification->id, $notifications));
        self::assertSame(' a répondu à ', $notifications[0]->title);
        self::assertSame([], $this->kit->repository->undigested('42', new DateTimeImmutable('2026-11-07 12:00 UTC'), 10));
        self::assertFalse($this->digests()->send('42', DigestFrequency::Daily));
    }

    public function testADigestHoldsUpToTheLimit(): void
    {
        $this->kit->preferences->settings['42'] = new RecipientSettings(DigestFrequency::Weekly);
        $this->keep('a', '42', '-6 days');
        $this->keep('b', '42', '-5 days');
        $this->keep('c', '42', '-4 days');

        self::assertTrue($this->digests(2)->send('42', DigestFrequency::Weekly));
        self::assertCount(2, $this->sender->sent[0][2]);
        self::assertTrue($this->digests(2)->send('42', DigestFrequency::Weekly));
        self::assertSame('c', $this->sender->sent[1][2][0]->notification->id);
    }

    public function testNoDigestForAnotherFrequencyNoAddressOrNothingNew(): void
    {
        $this->kit->preferences->settings['42'] = new RecipientSettings(DigestFrequency::Daily);
        $this->kit->preferences->settings['43'] = new RecipientSettings(DigestFrequency::Daily);
        $this->keep('a', '42', '-1 hour');
        $this->keep('b', '43', '-1 hour');

        self::assertFalse($this->digests()->send('42', DigestFrequency::Weekly));
        self::assertFalse($this->digests()->send('42', DigestFrequency::Never));
        self::assertFalse($this->digests()->send('43', DigestFrequency::Daily));
        self::assertFalse($this->digests()->send('44', DigestFrequency::Daily));
        self::assertSame([], $this->sender->sent);
        self::assertCount(1, $this->kit->repository->undigested('43', new DateTimeImmutable('2026-11-01'), 10));
    }

    public function testTheRecipientWithAnAddressButNothingGetsNoDigest(): void
    {
        $this->kit->preferences->settings['42'] = new RecipientSettings(DigestFrequency::Daily);

        self::assertFalse($this->digests()->send('42', DigestFrequency::Daily));
    }

    public function testASweepSendsTheRecipientsOfItsFrequency(): void
    {
        $this->kit->preferences->settings['42'] = new RecipientSettings(DigestFrequency::Daily);
        $this->kit->preferences->settings['43'] = new RecipientSettings(DigestFrequency::Weekly);
        $this->kit->preferences->settings['45'] = new RecipientSettings(DigestFrequency::Daily);
        $this->keep('a', '42', '-1 hour');
        $this->keep('b', '43', '-1 hour');
        $this->keep('c', '44', '-1 hour');
        $this->keep('d', '45', '-1 hour');

        self::assertSame(2, $this->digests()->sweep(DigestFrequency::Daily), '42 and 45 chose a daily digest');
        self::assertCount(1, $this->sender->sent, 'the directory has no address for 45');
        self::assertSame('a', $this->sender->sent[0][2][0]->notification->id);
        self::assertSame(0, $this->digests()->sweep(DigestFrequency::Never));
    }

    public function testASweepGoesThroughEveryPage(): void
    {
        for ($i = 0; $i < Digests::PAGE + 1; ++$i) {
            $recipient = \sprintf('r%03d', $i);
            $this->kit->preferences->settings[$recipient] = new RecipientSettings(DigestFrequency::Daily);
            $this->keep('n'.$i, $recipient, '-1 hour');
        }

        $bus = new RecordingBus();

        self::assertSame(Digests::PAGE + 1, $this->digests(bus: $bus)->sweep(DigestFrequency::Daily));
        self::assertCount(Digests::PAGE + 1, $bus->messages);
        self::assertEquals(new SendDigest('r200', DigestFrequency::Daily), $bus->messages[200]);
        self::assertSame([], $this->sender->sent);
    }

    public function testTheHandlersCallTheDigests(): void
    {
        $this->kit->preferences->settings['42'] = new RecipientSettings(DigestFrequency::Daily);
        $this->keep('a', '42', '-1 hour');
        $handler = new DigestHandler($this->digests());

        $handler->send(new SendDigest('42', DigestFrequency::Daily));
        self::assertCount(1, $this->sender->sent);

        $this->keep('b', '42', '-1 hour');
        $handler->sweep(new SendDigests(DigestFrequency::Daily));
        self::assertSame(2, $this->sender->count());
    }

    public function testTheRetentionPurgesInBatches(): void
    {
        $this->keep('read-old', '42', '-91 days')->markRead(new DateTimeImmutable());
        $this->keep('read-new', '42', '-89 days')->markRead(new DateTimeImmutable());
        $this->keep('unread-old', '42', '-366 days');
        $this->keep('unread-new', '42', '-364 days');
        $this->keep('archived-old', '42', '-100 days')->archive();

        self::assertSame(3, new Retention($this->kit->repository, $this->kit->clock, 90, 365, 1, 10)->purge());
        self::assertSame(['read-new', 'unread-new'], $this->ids());
    }

    public function testTheRetentionStopsAfterItsBatches(): void
    {
        foreach (['a', 'b', 'c', 'd', 'e'] as $id) {
            $this->keep($id, '42', '-400 days');
        }

        self::assertSame(4, new Retention($this->kit->repository, $this->kit->clock, 90, 365, 2, 2)->purge());
        self::assertCount(1, $this->ids());
        self::assertSame(1, new Retention($this->kit->repository, $this->kit->clock, 90, 365, 2, 2)->purge());
    }

    public function testTheRetentionHasDefaults(): void
    {
        $this->keep('a', '42', '-366 days');
        $this->keep('b', '42', '-364 days');
        $this->keep('c', '42', '-91 days')->markRead(new DateTimeImmutable());
        $this->keep('d', '42', '-89 days')->markRead(new DateTimeImmutable());

        new PurgeNotificationsHandler(new Retention($this->kit->repository, $this->kit->clock, 90, 365))();

        self::assertSame(['b', 'd'], $this->ids());
        self::assertSame([1000, 100], [Retention::BATCH, Retention::BATCHES]);
    }

    public function testTheRetentionStopsAtTheFirstShortBatch(): void
    {
        $repository = new CountingPurges($this->kit->repository);
        $this->keep('a', '42', '-400 days');
        $this->keep('b', '42', '-400 days');
        $this->keep('c', '42', '-400 days');

        self::assertSame(3, new Retention($repository, $this->kit->clock, 90, 365, 2, 10)->purge());
        self::assertSame(2, $repository->purges);
    }

    /**
     * @return list<string>
     */
    private function ids(): array
    {
        $ids = array_map(static fn (Notification $n): string => $n->id, $this->kit->repository->latest('42', 50));
        sort($ids);

        return $ids;
    }

    private function keep(string $id, string $recipient, string $ago): Notification
    {
        return $this->kit->repository->record(new Notification($id, 'comment.replied', $recipient, null, $this->kit->clock->now()->modify($ago)), false);
    }

    /**
     * @param positive-int $limit
     */
    private function digests(int $limit = 50, ?RecordingBus $bus = null): Digests
    {
        return new Digests($this->kit->repository, $this->kit->preferences, new Directory(), $this->kit->renderer(), $this->sender, $this->kit->clock, $limit, $bus);
    }
}
