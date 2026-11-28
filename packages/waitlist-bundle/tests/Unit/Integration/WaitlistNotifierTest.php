<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Tests\Unit\Integration;

use DateTimeImmutable;
use Phil\NotificationsBundle\Application\Handler\SendNotificationHandler;
use Phil\NotificationsBundle\Application\Message\SendNotification;
use Phil\NotificationsBundle\Application\NotificationDispatcher;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\NotificationTypes;
use Phil\WaitlistBundle\Domain\Event\EntryJoined;
use Phil\WaitlistBundle\Domain\Event\OfferAccepted;
use Phil\WaitlistBundle\Domain\Event\OfferLapsed;
use Phil\WaitlistBundle\Domain\Event\PlaceOffered;
use Phil\WaitlistBundle\Domain\Model\Entry;
use Phil\WaitlistBundle\Domain\Model\Waitlist;
use Phil\WaitlistBundle\Domain\Status\WaitlistMode;
use Phil\WaitlistBundle\Integration\Notifications\WaitlistNotifier;
use Phil\WaitlistBundle\Tests\Unit\Fixture\InMemoryEntries;
use Phil\WaitlistBundle\Tests\Unit\Fixture\RecordingBus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(WaitlistNotifier::class)]
final class WaitlistNotifierTest extends TestCase
{
    private RecordingBus $bus;

    private InMemoryEntries $entries;

    private Entry $entry;

    protected function setUp(): void
    {
        $this->bus = new RecordingBus();
        $now = new DateTimeImmutable('2026-11-01 12:00:00 UTC');
        $this->entries = new InMemoryEntries();
        $this->entry = Entry::join(Waitlist::open('session', 'S-1', WaitlistMode::Queue, 3_600, $now), 'account-7', 1, $now);
        $this->entries->save($this->entry);
    }

    public function testAnOfferIsSentToTheParticipantAboutTheEntry(): void
    {
        $this->notifierFor([PlaceOffered::class => 'waitlist.offered'], 'waitlist.offered')(new PlaceOffered($this->entry->id, $this->entry->waitlist()->id));

        self::assertCount(1, $this->bus->sent);
        $message = $this->bus->sent[0];
        self::assertInstanceOf(SendNotification::class, $message);
        self::assertSame(
            ['waitlist.offered', ['account-7'], 'waitlist_entry', $this->entry->id, ['waitlist' => $this->entry->waitlist()->id], 'waitlist.offered:'.$this->entry->id],
            [$message->type, $message->recipients, $message->subjectType, $message->subjectId, $message->data, $message->dedupeKey],
        );
    }

    public function testEachEventSendsItsOwnType(): void
    {
        $notifier = $this->notifierFor([EntryJoined::class => 'waitlist.joined', OfferAccepted::class => 'waitlist.accepted', OfferLapsed::class => 'waitlist.lapsed'], 'waitlist.joined', 'waitlist.accepted', 'waitlist.lapsed');
        $waitlistId = $this->entry->waitlist()->id;

        $notifier(new EntryJoined($this->entry->id, $waitlistId));
        $notifier(new OfferAccepted($this->entry->id, $waitlistId));
        $notifier(new OfferLapsed($this->entry->id, $waitlistId));
        $notifier(new PlaceOffered($this->entry->id, $waitlistId));

        self::assertSame(['waitlist.joined', 'waitlist.accepted', 'waitlist.lapsed'], array_map(static fn (object $message): string => $message instanceof SendNotification ? $message->type : '', $this->bus->sent));
    }

    public function testATypeNotDeclaredOrAMissingEntrySendsNothing(): void
    {
        $notifier = $this->notifierFor([PlaceOffered::class => 'waitlist.offered', OfferLapsed::class => 'waitlist.lapsed'], 'waitlist.lapsed');

        $notifier(new PlaceOffered($this->entry->id, $this->entry->waitlist()->id));
        $notifier(new OfferLapsed('missing', $this->entry->waitlist()->id));

        self::assertSame([], $this->bus->sent);
    }

    /**
     * @param array<class-string, string|null> $byEvent
     */
    private function notifierFor(array $byEvent, string ...$declared): WaitlistNotifier
    {
        // The bus takes every message: the handler and its dispatcher are never asked.
        $dispatcher = new ReflectionClass(NotificationDispatcher::class)->newInstanceWithoutConstructor();
        $types = new NotificationTypes(...array_map(static fn (string $key): NotificationType => new NotificationType($key), $declared));

        return new WaitlistNotifier(new Notifications($types, new SendNotificationHandler($dispatcher), $this->bus), $types, $this->entries, $byEvent);
    }
}
