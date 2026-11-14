<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Application;

use Phil\NotificationsBundle\Application\NotificationDispatcher;
use Phil\NotificationsBundle\Application\NotificationRenderer;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\NotificationTypes;
use Phil\NotificationsBundle\Infrastructure\Memory\InMemoryNotificationRepository;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Directory;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Preferences;
use Phil\NotificationsBundle\Tests\Unit\Fixture\RecordingNotifier;
use Phil\NotificationsBundle\Tests\Unit\Fixture\RecordingToasts;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Subjects;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Translator;
use Symfony\Component\Clock\MockClock;

/**
 * The application's services on memory and recording fixtures.
 */
final class Kit
{
    public InMemoryNotificationRepository $repository;

    public Preferences $preferences;

    public RecordingNotifier $notifier;

    public MockClock $clock;

    public NotificationTypes $types;

    public function __construct(NotificationType ...$types)
    {
        $this->repository = new InMemoryNotificationRepository();
        $this->preferences = new Preferences();
        $this->notifier = new RecordingNotifier();
        $this->clock = new MockClock('2026-11-08 12:00:00 UTC');
        $this->types = new NotificationTypes(...$types);
    }

    public function renderer(): NotificationRenderer
    {
        return new NotificationRenderer(Translator::make(), $this->types, new Directory(), [new Subjects()]);
    }

    public function dispatcher(): NotificationDispatcher
    {
        return new NotificationDispatcher($this->types, $this->repository, $this->preferences, $this->renderer(), new Directory(), new RecordingToasts(), $this->clock, $this->notifier);
    }
}
