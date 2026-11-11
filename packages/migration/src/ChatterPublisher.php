<?php

declare(strict_types=1);

namespace Phil\Migration;

use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

/**
 * Publishes through the chatter of `symfony/notifier`: a Discord, Slack or Mattermost transport, named as the
 * notifier names it.
 */
final readonly class ChatterPublisher implements Publisher
{
    public function __construct(
        private ChatterInterface $chatter,
    ) {
    }

    public function publish(string $transport, string $message): void
    {
        $this->chatter->send(new ChatMessage($message)->transport($transport));
    }
}
