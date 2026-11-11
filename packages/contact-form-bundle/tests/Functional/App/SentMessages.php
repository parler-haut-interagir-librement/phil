<?php

declare(strict_types=1);

namespace Phil\ContactFormBundle\Tests\Functional\App;

use Override;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\RawMessage;

/**
 * The messages the mailer sends, for the tests to read.
 */
final class SentMessages implements EventSubscriberInterface
{
    /** @var list<RawMessage> */
    public array $messages = [];

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [MessageEvent::class => 'record'];
    }

    public function record(MessageEvent $event): void
    {
        $this->messages[] = $event->getMessage();
    }
}
