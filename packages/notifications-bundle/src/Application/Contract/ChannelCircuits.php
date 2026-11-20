<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Contract;

/**
 * The circuit breakers of the channels of the `first_success` mode: `ChannelBreakers` on phil/circuit-breaker when it
 * is installed, or the application's own. A channel without a circuit is never open and records nothing.
 */
interface ChannelCircuits
{
    /**
     * Whether the channel is held back: its circuit is open. A circuit that lets a probe through is not.
     */
    public function isOpen(string $channel): bool;

    /**
     * The provider of the channel accepted a notification.
     */
    public function recordSuccess(string $channel): void;

    /**
     * The provider of the channel refused a notification, or did not answer.
     */
    public function recordFailure(string $channel): void;
}
