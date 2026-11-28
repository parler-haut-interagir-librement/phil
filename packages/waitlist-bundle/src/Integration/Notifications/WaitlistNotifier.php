<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Integration\Notifications;

use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Application\NotificationTypes;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Phil\WaitlistBundle\Domain\Contract\EntryRepository;
use Phil\WaitlistBundle\Domain\Event\EntryJoined;
use Phil\WaitlistBundle\Domain\Event\OfferAccepted;
use Phil\WaitlistBundle\Domain\Event\OfferLapsed;
use Phil\WaitlistBundle\Domain\Event\PlaceOffered;
use Phil\WaitlistBundle\Domain\Model\Entry;

/**
 * Tells an entry's participant what happened to it, through phil/notifications-bundle: a place offered, an offer
 * lapsed, and, when the application names their types, an entry joined or an offer accepted. Each notification is
 * about the entry, `waitlist_entry:<id>`, with the list's identifier as data: the application's subject resolver
 * writes what it says, such as the subject and the offer's deadline, when it is shown.
 *
 * A type left null, or not declared to the notifications bundle, sends nothing.
 */
final readonly class WaitlistNotifier
{
    /** The kind of subject the notifications are about. */
    public const string SUBJECT = 'waitlist_entry';

    /**
     * @param array<class-string, string|null> $types the notification type of each event, by its class
     */
    public function __construct(
        private Notifications $notifications,
        private NotificationTypes $declared,
        private EntryRepository $entries,
        private array $types,
    ) {
    }

    public function __invoke(EntryJoined|PlaceOffered|OfferAccepted|OfferLapsed $event): void
    {
        $type = $this->types[$event::class] ?? null;

        if (null === $type || !$this->declared->has($type)) {
            return;
        }

        $entry = $this->entries->get($event->entryId);

        if (!$entry instanceof Entry) {
            return;
        }

        $this->notifications->send(
            $type,
            $entry->participant(),
            new SubjectReference(self::SUBJECT, $entry->id),
            ['waitlist' => $event->waitlistId],
            dedupeKey: $type.':'.$entry->id,
        );
    }
}
