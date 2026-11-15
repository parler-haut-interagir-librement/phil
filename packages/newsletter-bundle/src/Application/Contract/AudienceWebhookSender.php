<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Contract;

use Phil\NewsletterBundle\Application\Exception\WebhookNotDelivered;

/**
 * Sends one signed webhook of an audience event to a receiver.
 */
interface AudienceWebhookSender
{
    /**
     * @param string $id the same for every attempt of one event to one receiver
     *
     * @throws WebhookNotDelivered
     */
    public function send(string $url, string $body, string $id): void;
}
