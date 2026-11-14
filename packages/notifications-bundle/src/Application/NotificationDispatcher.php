<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application;

use Phil\NotificationsBundle\Application\Contract\NotificationPreferences;
use Phil\NotificationsBundle\Application\Contract\RecipientDirectory;
use Phil\NotificationsBundle\Application\Contract\ToastPublisher;
use Phil\NotificationsBundle\Application\Contract\WebPushSender;
use Phil\NotificationsBundle\Application\Message\SendNotification;
use Phil\NotificationsBundle\Application\Preferences\DigestFrequency;
use Phil\NotificationsBundle\Domain\Contract\NotificationRepository;
use Phil\NotificationsBundle\Domain\Model\Notification;
use Phil\NotificationsBundle\Domain\ValueObject\NotificationData;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\NoRecipient;
use Symfony\Component\Notifier\Recipient\RecipientInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Delivers a notification to one recipient on the channels of its type they did not turn off:
 *
 * - `site` keeps it in their centre, collapsed into a similar unread one when the type says so, and shows it as a toast
 *   with their new unread count;
 * - `email` is left to the digest when the recipient chose one, the type allows it and the centre keeps it;
 * - quiet hours hold back the toast and every channel that interrupts, all but the centre and email;
 * - `web_push` goes to the application's Web Push sender, and the other channels to Symfony Notifier, at the address
 *   the recipient directory gives.
 *
 * The broadcast channels of a type are posted to once, whoever the recipients are.
 */
final readonly class NotificationDispatcher
{
    public function __construct(
        private NotificationTypes $types,
        private NotificationRepository $repository,
        private NotificationPreferences $preferences,
        private NotificationRenderer $renderer,
        private RecipientDirectory $directory,
        private ToastPublisher $toasts,
        private ClockInterface $clock,
        private ?NotifierInterface $notifier = null,
        private ?WebPushSender $webPush = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function deliver(SendNotification $message, string $recipient): void
    {
        $type = $this->types->get($message->type);
        $settings = $this->preferences->settingsFor($recipient);
        $now = $this->clock->now();
        $notification = new Notification(Uuid::v7()->toRfc4122(), $type->key, $recipient, $message->subject(), $now, $message->actor, NotificationData::from($message->data), $message->origin);
        $channels = array_filter($type->channels, static fn (string $channel): bool => $settings->allows($type->key, $channel));
        $quiet = $settings->quietHours?->contains($now) ?? false;
        $kept = null;

        if (\in_array(Channels::SITE, $channels, true)) {
            $kept = $this->repository->record($notification, $type->collapse);

            if ($type->toast && !$quiet) {
                $this->toasts->publish($this->renderer->render($kept, $settings->locale), $this->repository->unreadCount($recipient));
            }
        }

        $digested = $kept instanceof Notification && $type->digest && DigestFrequency::Never !== $settings->digest;
        $others = array_filter($channels, static fn (string $channel): bool => Channels::SITE !== $channel
            && (!$digested || Channels::EMAIL !== $channel)
            && (!$quiet || !Channels::interrupts($channel)));

        if ([] === $others) {
            return;
        }

        $rendered = $this->renderer->render($kept ?? $notification, $settings->locale);

        if (\in_array(Channels::WEB_PUSH, $others, true)) {
            $this->webPush($recipient, $rendered);
        }

        $notifierChannels = array_values(array_diff($others, [Channels::WEB_PUSH]));

        if ([] === $notifierChannels) {
            return;
        }

        $contact = $this->directory->contact($recipient);

        if (!$contact instanceof RecipientInterface) {
            $this->logger?->notice('The notification "{type}" was not sent to "{recipient}" on {channels}: the recipient directory has no address for them.', [
                'type' => $type->key,
                'recipient' => $recipient,
                'channels' => implode(', ', $notifierChannels),
            ]);

            return;
        }

        $this->notify($rendered, $type, $notifierChannels, $contact);
    }

    public function broadcast(SendNotification $message): void
    {
        $type = $this->types->get($message->type);

        if ([] === $type->broadcast) {
            return;
        }

        $notification = new Notification(Uuid::v7()->toRfc4122(), $type->key, 'broadcast', $message->subject(), $this->clock->now(), $message->actor, NotificationData::from($message->data), $message->origin);
        $this->notify($this->renderer->render($notification), $type, $type->broadcast, new NoRecipient());
    }

    private function webPush(string $recipient, RenderedNotification $rendered): void
    {
        if (!$this->webPush instanceof WebPushSender) {
            $this->logger?->notice('The notification "{type}" was not sent by Web Push: no Web Push sender is configured.', ['type' => $rendered->notification->type]);

            return;
        }

        $this->webPush->send($recipient, $rendered);
    }

    /**
     * @param list<string> $channels
     */
    private function notify(RenderedNotification $rendered, NotificationType $type, array $channels, RecipientInterface $contact): void
    {
        if (!$this->notifier instanceof NotifierInterface) {
            $this->logger?->warning('The notification "{type}" was not sent on {channels}: Symfony Notifier is not installed.', [
                'type' => $type->key,
                'channels' => implode(', ', $channels),
            ]);

            return;
        }

        $this->notifier->send(new OutgoingNotification($rendered->title, $channels)->content($rendered->body)->importance($type->importance), $contact);
    }
}
