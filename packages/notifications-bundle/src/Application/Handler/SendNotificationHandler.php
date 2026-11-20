<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Handler;

use Phil\NotificationsBundle\Application\Contract\AudienceProvider;
use Phil\NotificationsBundle\Application\Exception\DeliveryFailed;
use Phil\NotificationsBundle\Application\Exception\UnknownAudience;
use Phil\NotificationsBundle\Application\Message\SendNotification;
use Phil\NotificationsBundle\Application\NotificationDispatcher;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Delivers a notification: to its one recipient at once; to several, or to an audience, through one message per
 * recipient when a bus carries them, so that each is retried alone, or one after the other without a bus. The broadcast
 * channels are posted to once, by the message the application sent.
 *
 * Without a bus, a recipient no channel reached does not stop the others: the first `DeliveryFailed` is thrown once
 * every recipient was served, before the broadcast, which the retry posts once the recipients are reached.
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
     * @throws DeliveryFailed  when no channel reached a recipient and a later attempt may
     */
    public function __invoke(SendNotification $message): void
    {
        $failure = null;

        if (null === $message->audience && 1 === \count($message->recipients)) {
            $failure = $this->deliver($message, $message->recipients[0]);
        } else {
            foreach ($this->recipients($message) as $recipient) {
                if (!$this->bus instanceof MessageBusInterface) {
                    $failed = $this->deliver($message, $recipient);
                    $failure ??= $failed;
                } else {
                    $this->bus->dispatch($message->for($recipient));
                }
            }
        }

        if ($failure instanceof DeliveryFailed) {
            throw $failure;
        }

        if ($message->broadcast) {
            $this->dispatcher->broadcast($message);
        }
    }

    private function deliver(SendNotification $message, string $recipient): ?DeliveryFailed
    {
        try {
            $this->dispatcher->deliver($message, $recipient);
        } catch (DeliveryFailed $deliveryFailed) {
            return $deliveryFailed;
        }

        return null;
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
