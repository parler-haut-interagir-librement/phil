<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Handler;

use DateTimeImmutable;
use Phil\NewsletterBundle\Application\Contract\AudienceWebhookSender;
use Phil\NewsletterBundle\Application\Exception\WebhookNotDelivered;
use Phil\NewsletterBundle\Application\Message\DeliverAudienceWebhook;
use Phil\NewsletterBundle\Domain\Contract\MailingListRepository;
use Phil\NewsletterBundle\Domain\Model\MailingList;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Sends an audience event to a configured receiver, as a Standard Webhooks delivery: the subscriber's id and external
 * id, the site and the list's key, never the address. Every attempt of one event to one receiver has the same id. A
 * failure worth retrying is thrown for Messenger's retries; another is logged and dropped.
 */
#[AsMessageHandler]
final readonly class DeliverAudienceWebhookHandler
{
    private LoggerInterface $logger;

    /**
     * @param array<string, string> $endpoints the receivers' addresses, by name
     */
    public function __construct(
        private AudienceWebhookSender $sender,
        private MailingListRepository $lists,
        private array $endpoints,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function __invoke(DeliverAudienceWebhook $message): void
    {
        $url = $this->endpoints[$message->endpoint] ?? null;

        if (null === $url) {
            return;
        }

        $list = null === $message->listId ? null : $this->lists->get($message->listId);
        $body = json_encode([
            'type' => 'newsletter.subscriber.'.$message->type,
            'timestamp' => new DateTimeImmutable('@'.$message->occurredAt)->format(\DATE_RFC3339),
            'data' => [
                'subscriber' => $message->subscriberId,
                'external_id' => $message->externalId,
                'site' => $message->site,
                'list' => $list instanceof MailingList ? $list->key : null,
            ],
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        try {
            $this->sender->send($url, $body, 'msg_'.$message->eventId.'_'.substr(hash('sha256', $message->endpoint), 0, 8));
        } catch (WebhookNotDelivered $webhookNotDelivered) {
            if ($webhookNotDelivered->retryable) {
                throw $webhookNotDelivered;
            }

            $this->logger->warning('The audience webhook {event} to {endpoint} was refused.', ['event' => $message->eventId, 'endpoint' => $message->endpoint]);
        }
    }
}
