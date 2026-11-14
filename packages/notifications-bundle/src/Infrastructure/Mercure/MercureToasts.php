<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Mercure;

use Override;
use Phil\NotificationsBundle\Application\Contract\ToastPublisher;
use Phil\NotificationsBundle\Application\RenderedNotification;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Pushes a toast and the new unread count to the recipient's own topic of a Mercure hub, as JSON:
 * `{"unread": 3, "toast": {"id", "type", "title", "body", "url", "count"}}`. Updates are private unless configured
 * otherwise: the application authorizes each recipient to subscribe to their own topic only. A hub that cannot be
 * reached, or cannot publish at all — FrankenPHP's built-in hub outside a request it serves, in a command or a
 * Messenger consumer —, is logged and does not fail the delivery: the notification is in the centre.
 */
final readonly class MercureToasts implements ToastPublisher
{
    public const string PREFIX = 'urn:phil:notifications:';

    public function __construct(
        private HubInterface $hub,
        private string $prefix = self::PREFIX,
        private bool $private = true,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * The topic of a recipient.
     */
    public function topic(string $recipient): string
    {
        return $this->prefix.$recipient;
    }

    #[Override]
    public function publish(RenderedNotification $notification, int $unread): void
    {
        $recipient = $notification->notification->recipient;

        try {
            $this->hub->publish(new Update(
                $this->topic($recipient),
                json_encode(['unread' => $unread, 'toast' => $notification->toArray()], \JSON_THROW_ON_ERROR),
                $this->private,
            ));
        } catch (RuntimeException $runtimeException) {
            $this->logger?->warning('The toast of "{recipient}" could not be pushed to Mercure: {message}', [
                'recipient' => $recipient,
                'message' => $runtimeException->getMessage(),
                'exception' => $runtimeException,
            ]);
        }
    }
}
