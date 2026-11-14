<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application;

use DateTimeImmutable;
use Phil\NotificationsBundle\Application\Contract\DigestSender;
use Phil\NotificationsBundle\Application\Contract\NotificationPreferences;
use Phil\NotificationsBundle\Application\Contract\RecipientDirectory;
use Phil\NotificationsBundle\Application\Message\SendDigest;
use Phil\NotificationsBundle\Application\Preferences\DigestFrequency;
use Phil\NotificationsBundle\Domain\Contract\NotificationRepository;
use Phil\NotificationsBundle\Domain\Model\Notification;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * The email digests: a sweep finds, a page at a time, the recipients with unread notifications no digest held, changed
 * within the frequency's window, and sends each who chose that frequency their digest — through one message each when a
 * bus carries them. A digest holds the oldest unread notifications first, up to the limit, and marks them sent: the next
 * digest holds the rest, and a notification collapsed into after it was sent comes back.
 */
final readonly class Digests
{
    public const int PAGE = 200;

    /**
     * @param positive-int $limit how many notifications a digest holds at most
     */
    public function __construct(
        private NotificationRepository $repository,
        private NotificationPreferences $preferences,
        private RecipientDirectory $directory,
        private NotificationRenderer $renderer,
        private DigestSender $sender,
        private ClockInterface $clock,
        private int $limit,
        private ?MessageBusInterface $bus = null,
    ) {
    }

    /**
     * @return int how many recipients a digest was sent to, or dispatched for
     */
    public function sweep(DigestFrequency $frequency): int
    {
        $since = $this->since($frequency);

        if (!$since instanceof DateTimeImmutable) {
            return 0;
        }

        $sent = 0;
        $after = null;

        do {
            $recipients = $this->repository->awaitingDigest($since, $after, self::PAGE);

            foreach ($recipients as $recipient) {
                $after = $recipient;

                if ($frequency !== $this->preferences->settingsFor($recipient)->digest) {
                    continue;
                }

                ++$sent;

                if (!$this->bus instanceof MessageBusInterface) {
                    $this->send($recipient, $frequency);
                } else {
                    $this->bus->dispatch(new SendDigest($recipient, $frequency));
                }
            }
        } while (self::PAGE === \count($recipients));

        return $sent;
    }

    /**
     * @return bool whether a digest was sent
     */
    public function send(string $recipient, DigestFrequency $frequency): bool
    {
        $since = $this->since($frequency);
        $settings = $this->preferences->settingsFor($recipient);

        if (!$since instanceof DateTimeImmutable || $frequency !== $settings->digest) {
            return false;
        }

        $contact = $this->directory->contact($recipient);
        $notifications = $this->repository->undigested($recipient, $since, $this->limit);

        if (!$contact instanceof RecipientInterface || [] === $notifications) {
            return false;
        }

        $this->sender->send($contact, $frequency, array_map(fn (Notification $n): RenderedNotification => $this->renderer->render($n, $settings->locale), $notifications), $settings->locale);
        $this->repository->markDigested($recipient, array_map(static fn (Notification $n): string => $n->id, $notifications), $this->clock->now());

        return true;
    }

    private function since(DigestFrequency $frequency): ?DateTimeImmutable
    {
        $window = $frequency->window();

        return null === $window ? null : $this->clock->now()->modify($window);
    }
}
