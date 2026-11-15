<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Handler;

use Phil\NewsletterBundle\Application\Bounces\BounceHandler;
use Phil\NewsletterBundle\Application\Contract\BounceMailbox;
use Phil\NewsletterBundle\Application\Message\FetchBounces;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Reads the bounce mailbox: each unread message handled, then marked so that it is not read again.
 */
#[AsMessageHandler]
final readonly class FetchBouncesHandler
{
    public function __construct(
        private BounceMailbox $mailbox,
        private BounceHandler $bounces,
    ) {
    }

    /**
     * @return int how many messages were handled
     */
    public function __invoke(FetchBounces $message): int
    {
        $handled = 0;

        foreach ($this->mailbox->unread(max(1, $message->limit)) as $id => $raw) {
            $this->bounces->handleMessage($raw);
            $this->mailbox->acknowledge((string) $id);
            ++$handled;
        }

        return $handled;
    }
}
