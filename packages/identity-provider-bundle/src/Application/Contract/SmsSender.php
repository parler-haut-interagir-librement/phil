<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Contract;

use Phil\IdentityProviderBundle\Application\Exception\DeliveryFailed;

/**
 * Sends a text message to a phone number in E.164. The bundle's adapter goes through the Notifier component's texter;
 * an application replaces it to use its own gateway.
 */
interface SmsSender
{
    /**
     * @throws DeliveryFailed when the message could not be handed to the gateway
     */
    public function send(string $phone, string $message): void;
}
