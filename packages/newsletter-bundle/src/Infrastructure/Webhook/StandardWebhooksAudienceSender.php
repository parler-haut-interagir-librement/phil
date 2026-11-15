<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Webhook;

use Override;
use Phil\NewsletterBundle\Application\Contract\AudienceWebhookSender;
use Phil\NewsletterBundle\Application\Exception\WebhookNotDelivered;
use Phil\StandardWebhooks\WebhookId;
use Phil\StandardWebhooksBundle\Infrastructure\Http\DeliveryFailed;
use Phil\StandardWebhooksBundle\Infrastructure\Http\WebhookSender;

/**
 * The audience webhooks sent by `phil/standard-webhooks-bundle`'s sender: signed with its keys, `https` only, behind
 * its guard against private networks.
 */
final readonly class StandardWebhooksAudienceSender implements AudienceWebhookSender
{
    public function __construct(
        private WebhookSender $sender,
    ) {
    }

    #[Override]
    public function send(string $url, string $body, string $id): void
    {
        try {
            $this->sender->send($url, $body, WebhookId::fromString($id));
        } catch (DeliveryFailed $deliveryFailed) {
            throw new WebhookNotDelivered('The receiver did not accept the audience webhook.', $deliveryFailed->retryable, $deliveryFailed);
        }
    }
}
