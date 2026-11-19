<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Infrastructure\Mercure;

use Phil\ConversationBundle\Application\Event\MessageSent;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Tells the open pages of the author and of each recipient that a message came, through a Mercure hub: a private update
 * on each one's own topic, `{"conversation": "…", "message": "…", "sequence": 42}`, identifiers only, the page reading
 * the message from the bundle. The application authorizes each participant to subscribe to their own topic only. A hub
 * that cannot be reached, or cannot publish outside a request it serves, is logged and does not fail the message: it is
 * kept, and the page shows it when it is loaded again.
 */
final readonly class MercureMessagePublisher
{
    public const string PREFIX = 'urn:phil:conversation:';

    public function __construct(
        private HubInterface $hub,
        private string $prefix = self::PREFIX,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * The topic of a participant.
     */
    public function topic(string $participant): string
    {
        return $this->prefix.$participant;
    }

    public function __invoke(MessageSent $event): void
    {
        $data = json_encode(['conversation' => $event->conversation, 'message' => $event->message, 'sequence' => $event->sequence], \JSON_THROW_ON_ERROR);
        $topics = array_map($this->topic(...), [$event->author, ...$event->recipients]);

        try {
            $this->hub->publish(new Update($topics, $data, true));
        } catch (RuntimeException $runtimeException) {
            $this->logger?->warning('The message "{message}" of the conversation "{conversation}" could not be pushed to Mercure: {reason}', [
                'message' => $event->message,
                'conversation' => $event->conversation,
                'reason' => $runtimeException->getMessage(),
                'exception' => $runtimeException,
            ]);
        }
    }
}
