<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application;

use Phil\NotificationsBundle\Application\Contract\ChannelSender;
use Phil\NotificationsBundle\Application\Contract\DeliveryDedupe;
use Phil\NotificationsBundle\Application\Contract\NotificationPreferences;
use Phil\NotificationsBundle\Application\Contract\RecipientDirectory;
use Phil\NotificationsBundle\Application\Contract\ToastPublisher;
use Phil\NotificationsBundle\Application\Contract\WebPushSender;
use Phil\NotificationsBundle\Application\Delivery\ChannelOutcome;
use Phil\NotificationsBundle\Application\Delivery\DedupeTicket;
use Phil\NotificationsBundle\Application\Delivery\DeliveryMode;
use Phil\NotificationsBundle\Application\Delivery\DeliveryPolicy;
use Phil\NotificationsBundle\Application\Delivery\DeliveryResult;
use Phil\NotificationsBundle\Application\Delivery\DeliveryStatus;
use Phil\NotificationsBundle\Application\Delivery\NotificationDelivered;
use Phil\NotificationsBundle\Application\Delivery\RecipientRateLimit;
use Phil\NotificationsBundle\Application\Delivery\SkipReason;
use Phil\NotificationsBundle\Application\Exception\DeliveryFailed;
use Phil\NotificationsBundle\Application\Message\SendNotification;
use Phil\NotificationsBundle\Application\Preferences\DigestFrequency;
use Phil\NotificationsBundle\Domain\Contract\NotificationRepository;
use Phil\NotificationsBundle\Domain\Model\Notification;
use Phil\NotificationsBundle\Domain\ValueObject\NotificationData;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\NoRecipient;
use Symfony\Component\Notifier\Recipient\RecipientInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * Delivers a notification to one recipient on the channels of its type they did not turn off:
 *
 * - `site` keeps it in their centre, collapsed into a similar unread one when the type says so, and shows it as a toast
 *   with their new unread count;
 * - `email` is left to the digest when the recipient chose one, the type allows it and the centre keeps it;
 * - quiet hours hold back the toast and every channel that interrupts, all but the centre and email;
 * - in the `fan_out` mode, `web_push` goes to the application's Web Push sender, and the other channels to Symfony
 *   Notifier, at the address the recipient directory gives;
 * - in the `first_success` mode, the channels are tried one after the other, in the order of the type, through the
 *   Web Push sender and the channel senders, at once, and the first that accepts the notification ends the delivery.
 *   A channel without an address, without a sender or behind an open circuit is skipped; one whose transport refuses
 *   the notification counts as a failure of its circuit and makes way for the next.
 *
 * Before any channel outside the centre, the same notification already delivered to the recipient within its window
 * is a duplicate, and a recipient past their rate limit gets it in the centre only, unless the type is `urgent`. Each
 * delivery ends with a `DeliveryResult`, dispatched as `NotificationDelivered` and logged without the recipient; when no
 * channel took the notification and one was refused or held back by its circuit, `DeliveryFailed` is thrown last, for
 * Messenger to try again.
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
        private DeliveryPolicy $policy = new DeliveryPolicy(),
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * @throws DeliveryFailed when no channel took the notification and a later attempt may
     */
    public function deliver(SendNotification $message, string $recipient): DeliveryResult
    {
        $type = $this->types->get($message->type);
        $dedupe = $this->policy->dedupe;
        $ticket = $dedupe?->begin($message, $type, $recipient);

        if (true === $ticket?->duplicate) {
            return $this->finish(new DeliveryResult($type->key, $recipient, $type->delivery, DeliveryStatus::Duplicate));
        }

        try {
            $result = $this->attempt($message, $type, $recipient);
        } catch (Throwable $throwable) {
            if ($ticket instanceof DedupeTicket) {
                $dedupe->release($ticket, $type);
            }

            throw $throwable;
        }

        if ($ticket instanceof DedupeTicket) {
            if (DeliveryStatus::Sent === $result->status) {
                $dedupe->complete($ticket, $type);
            } else {
                $dedupe->release($ticket, $type);
            }
        }

        $this->finish($result);

        if ($result->isRetryable()) {
            throw DeliveryFailed::of($result);
        }

        return $result;
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

    private function attempt(SendNotification $message, NotificationType $type, string $recipient): DeliveryResult
    {
        $settings = $this->preferences->settingsFor($recipient);
        $now = $this->clock->now();
        $notification = new Notification(Uuid::v7()->toRfc4122(), $type->key, $recipient, $message->subject(), $now, $message->actor, NotificationData::from($message->data), $message->origin);
        $quiet = $settings->quietHours?->contains($now) ?? false;
        $channels = [];
        $skipped = [];
        $took = [];
        $kept = null;

        foreach ($type->channels as $channel) {
            if ($settings->allows($type->key, $channel)) {
                $channels[] = $channel;
            } else {
                $skipped[$channel] = SkipReason::OptedOut;
            }
        }

        if (\in_array(Channels::SITE, $channels, true)) {
            $kept = $this->keep($message, $type, $recipient, $notification);
            $took[] = Channels::SITE;

            if ($kept instanceof Notification && $type->toast && !$quiet) {
                $this->toasts->publish($this->renderer->render($kept, $settings->locale), $this->repository->unreadCount($recipient));
            }
        }

        $digested = [] !== $took && $type->digest && DigestFrequency::Never !== $settings->digest;
        $candidates = [];

        foreach ($channels as $channel) {
            if (Channels::SITE === $channel) {
                continue;
            }

            if ($quiet && Channels::interrupts($channel)) {
                $skipped[$channel] = SkipReason::QuietHours;
            } else {
                $candidates[] = $channel;
            }
        }

        $outside = array_values(array_filter($candidates, static fn (string $channel): bool => !$digested || Channels::EMAIL !== $channel));

        if ([] !== $outside && $this->limited($type, $recipient)) {
            return new DeliveryResult($type->key, $recipient, $type->delivery, DeliveryStatus::RateLimited, $took, $skipped);
        }

        if ([] === $candidates) {
            return new DeliveryResult($type->key, $recipient, $type->delivery, DeliveryStatus::Sent, $took, $skipped);
        }

        $rendered = $this->renderer->render($kept ?? $notification, $settings->locale);

        if (DeliveryMode::FirstSuccess === $type->delivery) {
            return $this->firstSuccess($type, $recipient, $rendered, $candidates, $digested, $took, $skipped);
        }

        if (\count($outside) !== \count($candidates)) {
            $took[] = Channels::EMAIL;
        }

        return $this->fanOut($type, $recipient, $rendered, $outside, $took, $skipped);
    }

    /**
     * Keeps the notification in the centre, once per window when duplicates are watched; null when a previous attempt
     * kept it already.
     */
    private function keep(SendNotification $message, NotificationType $type, string $recipient, Notification $notification): ?Notification
    {
        $dedupe = $this->policy->dedupe;

        if (!$dedupe instanceof DeliveryDedupe) {
            return $this->repository->record($notification, $type->collapse);
        }

        $kept = null;
        $dedupe->once($message, $type, $recipient, function () use (&$kept, $notification, $type): void {
            $kept = $this->repository->record($notification, $type->collapse);
        });

        return $kept;
    }

    private function limited(NotificationType $type, string $recipient): bool
    {
        $limit = $this->policy->rateLimit;

        return $limit instanceof RecipientRateLimit && 'urgent' !== $type->importance && !$limit->consume($recipient);
    }

    /**
     * @param list<string>              $channels
     * @param list<string>              $took
     * @param array<string, SkipReason> $skipped
     */
    private function fanOut(NotificationType $type, string $recipient, RenderedNotification $rendered, array $channels, array $took, array $skipped): DeliveryResult
    {
        if (\in_array(Channels::WEB_PUSH, $channels, true)) {
            $reason = $this->webPush($recipient, $rendered);

            if (!$reason instanceof SkipReason) {
                $took[] = Channels::WEB_PUSH;
            } else {
                $skipped[Channels::WEB_PUSH] = $reason;
            }
        }

        $notifierChannels = array_values(array_diff($channels, [Channels::WEB_PUSH]));

        if ([] !== $notifierChannels) {
            $contact = $this->directory->contact($recipient);

            if (!$contact instanceof RecipientInterface || !$this->notifier instanceof NotifierInterface) {
                $reason = $contact instanceof RecipientInterface ? SkipReason::Unavailable : SkipReason::NoContact;

                foreach ($notifierChannels as $channel) {
                    $skipped[$channel] = $reason;
                }
            } else {
                $this->notify($rendered, $type, $notifierChannels, $contact);
                $took = [...$took, ...$notifierChannels];
            }
        }

        return new DeliveryResult($type->key, $recipient, DeliveryMode::FanOut, DeliveryStatus::Sent, $took, $skipped);
    }

    /**
     * @param list<string>              $channels
     * @param list<string>              $took
     * @param array<string, SkipReason> $skipped
     */
    private function firstSuccess(NotificationType $type, string $recipient, RenderedNotification $rendered, array $channels, bool $digested, array $took, array $skipped): DeliveryResult
    {
        $contact = null;
        $looked = false;

        foreach ($channels as $channel) {
            if ($digested && Channels::EMAIL === $channel) {
                // The digest takes it from the centre: the email channel has done its part.
                $reason = null;
            } elseif (Channels::WEB_PUSH === $channel) {
                $reason = $this->guarded($channel, fn (): ?SkipReason => $this->webPush($recipient, $rendered));
            } else {
                if (!$looked) {
                    $contact = $this->directory->contact($recipient);
                    $looked = true;
                }

                $reason = $this->sendOn($channel, $type, $rendered, $contact);
            }

            if (!$reason instanceof SkipReason) {
                return new DeliveryResult($type->key, $recipient, DeliveryMode::FirstSuccess, DeliveryStatus::Sent, [...$took, $channel], $skipped);
            }

            $skipped[$channel] = $reason;
        }

        return new DeliveryResult($type->key, $recipient, DeliveryMode::FirstSuccess, DeliveryStatus::Failed, $took, $skipped);
    }

    /**
     * Sends on a channel of the channel senders; null when it was accepted, the reason otherwise.
     */
    private function sendOn(string $channel, NotificationType $type, RenderedNotification $rendered, ?RecipientInterface $contact): ?SkipReason
    {
        if (!Channels::reaches($channel, $contact)) {
            return SkipReason::NoContact;
        }

        $sender = $this->policy->sender($channel);

        if (!$sender instanceof ChannelSender) {
            return SkipReason::Unavailable;
        }

        return $this->guarded($channel, static fn (): ?SkipReason => self::reason($sender->send($channel, $rendered, $type, $contact)));
    }

    /**
     * Runs an attempt behind the circuit breaker of its channel, if any: skipped while it is open, a refusal recorded as
     * a failure, an acceptance as a success. An unreachable recipient says nothing of the provider.
     *
     * @param callable(): ?SkipReason $attempt
     */
    private function guarded(string $channel, callable $attempt): ?SkipReason
    {
        $circuits = $this->policy->circuits;

        if (true === $circuits?->isOpen($channel)) {
            return SkipReason::CircuitOpen;
        }

        $reason = $attempt();

        if (null === $reason) {
            $circuits?->recordSuccess($channel);
        } elseif (SkipReason::Refused === $reason) {
            $circuits?->recordFailure($channel);
        }

        return $reason;
    }

    private function webPush(string $recipient, RenderedNotification $rendered): ?SkipReason
    {
        if (!$this->webPush instanceof WebPushSender) {
            return SkipReason::Unavailable;
        }

        return self::reason($this->webPush->send($recipient, $rendered));
    }

    private static function reason(ChannelOutcome $outcome): ?SkipReason
    {
        return match ($outcome) {
            ChannelOutcome::Accepted => null,
            ChannelOutcome::Unreachable => SkipReason::NoContact,
            ChannelOutcome::Refused => SkipReason::Refused,
        };
    }

    private function finish(DeliveryResult $result): DeliveryResult
    {
        $this->events?->dispatch(new NotificationDelivered($result));

        if (DeliveryStatus::Failed === $result->status) {
            $this->logger?->warning('The notification "{type}" reached no channel ({mode}); skipped: {skipped}.', $result->context());
        } else {
            $this->logger?->info('The notification "{type}" ended {status} ({mode}) on {channels}; skipped: {skipped}.', $result->context());
        }

        return $result;
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
