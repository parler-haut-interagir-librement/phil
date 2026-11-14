<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Handler;

use Phil\NotificationsBundle\Application\Contract\AudienceProvider;
use Phil\NotificationsBundle\Application\Exception\UnknownAudience;
use Phil\NotificationsBundle\Application\Message\SendNotification;
use Phil\NotificationsBundle\Application\NotificationDispatcher;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Delivers a notification: to its one recipient at once; to several, or to an audience, through one message per
 * recipient when a bus carries them, so that each is retried alone, or one after the other without a bus. The broadcast
 * channels are posted to once, by the message the application sent.
 */
final readonly class SendNotificationHandler
{
    /**
     * @param iterable<AudienceProvider> $audiences
     */
    public function __construct(
        private NotificationDispatcher $dispatcher,
        private iterable $audiences = [],
        private ?MessageBusInterface $bus = null,
    ) {
    }

    /**
     * @throws UnknownAudience when no provider supports the audience
     */
    public function __invoke(SendNotification $message): void
    {
        if (null === $message->audience && 1 === \count($message->recipients)) {
            $this->dispatcher->deliver($message, $message->recipients[0]);
        } else {
            foreach ($this->recipients($message) as $recipient) {
                if (!$this->bus instanceof MessageBusInterface) {
                    $this->dispatcher->deliver($message, $recipient);
                } else {
                    $this->bus->dispatch($message->for($recipient));
                }
            }
        }

        if ($message->broadcast) {
            $this->dispatcher->broadcast($message);
        }
    }

    /**
     * @return iterable<string>
     */
    private function recipients(SendNotification $message): iterable
    {
        yield from $message->recipients;

        if (null === $message->audience) {
            return;
        }

        foreach ($this->audiences as $provider) {
            if ($provider->supports($message->audience)) {
                yield from $provider->recipients($message->audience);

                return;
            }
        }

        throw UnknownAudience::named($message->audience);
    }
}
