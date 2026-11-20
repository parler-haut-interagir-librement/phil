<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Delivery;

use Phil\NotificationsBundle\Application\Contract\ChannelCircuits;
use Phil\NotificationsBundle\Application\Contract\ChannelSender;
use Phil\NotificationsBundle\Application\Contract\DeliveryDedupe;

/**
 * What a delivery checks and sends through beyond the channels of Symfony Notifier: the senders `first_success` tries
 * a channel with, the window of duplicates, the rate limit of each recipient and the circuit breakers of the channels.
 * Each is optional: without a window, a limit or breakers, nothing is held back.
 */
final readonly class DeliveryPolicy
{
    /**
     * @param iterable<ChannelSender> $senders
     */
    public function __construct(
        public iterable $senders = [],
        public ?DeliveryDedupe $dedupe = null,
        public ?RecipientRateLimit $rateLimit = null,
        public ?ChannelCircuits $circuits = null,
    ) {
    }

    public function sender(string $channel): ?ChannelSender
    {
        foreach ($this->senders as $sender) {
            if ($sender->supports($channel)) {
                return $sender;
            }
        }

        return null;
    }
}
