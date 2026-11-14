<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application;

use Phil\NotificationsBundle\Application\Exception\UnknownNotificationType;
use Phil\NotificationsBundle\Application\Handler\SendNotificationHandler;
use Phil\NotificationsBundle\Application\Message\SendNotification;
use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * What the application calls to notify: a type, the recipients by identifier — or an audience —, what it is about, a
 * few more identifiers, and who caused it. Everything is checked here, so that a mistake fails where it is made; the
 * message then goes to the bus, which delivers it now or later as its routing says, or is delivered at once without a
 * bus.
 *
 * ```php
 * $notifications->send('comment.replied', [$author->id], new SubjectReference('comment', $reply->id), actor: $replier->id);
 * ```
 */
final readonly class Notifications
{
    public function __construct(
        private NotificationTypes $types,
        private SendNotificationHandler $handler,
        private ?MessageBusInterface $bus = null,
    ) {
    }

    /**
     * @param string|list<string>     $recipients
     * @param array<array-key, mixed> $data       identifiers only, by key
     *
     * @throws UnknownNotificationType when the type is not declared
     * @throws InvalidNotification     when an identifier is of the wrong shape
     */
    public function send(string $type, string|array $recipients, ?SubjectReference $subject = null, array $data = [], ?string $actor = null, ?string $origin = null): void
    {
        $this->dispatch(new SendNotification($this->types->get($type)->key, \is_string($recipients) ? [$recipients] : $recipients, null, $subject?->type, $subject?->id, $data, $actor, $origin));
    }

    /**
     * Notifies the recipients of an audience, which an audience provider names when the notification is delivered.
     *
     * @param array<array-key, mixed> $data identifiers only, by key
     *
     * @throws UnknownNotificationType when the type is not declared
     * @throws InvalidNotification     when an identifier is of the wrong shape
     */
    public function sendToAudience(string $type, string $audience, ?SubjectReference $subject = null, array $data = [], ?string $actor = null, ?string $origin = null): void
    {
        $this->dispatch(new SendNotification($this->types->get($type)->key, [], $audience, $subject?->type, $subject?->id, $data, $actor, $origin));
    }

    private function dispatch(SendNotification $message): void
    {
        if (!$this->bus instanceof MessageBusInterface) {
            ($this->handler)($message);

            return;
        }

        $this->bus->dispatch($message);
    }
}
