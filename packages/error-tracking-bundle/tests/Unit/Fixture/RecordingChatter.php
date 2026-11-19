<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;

final class RecordingChatter implements ChatterInterface
{
    /**
     * @var list<ChatMessage>
     */
    public array $messages = [];

    #[Override]
    public function send(MessageInterface $message): ?SentMessage
    {
        \assert($message instanceof ChatMessage);
        $this->messages[] = $message;

        return null;
    }

    #[Override]
    public function supports(MessageInterface $message): bool
    {
        return true;
    }

    #[Override]
    public function __toString(): string
    {
        return 'recording';
    }
}
