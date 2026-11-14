<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Tests\Functional\App;

use Phil\SocialCardsBundle\Infrastructure\Messenger\GenerateSocialCard;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;

/**
 * A listener of the event dispatcher that keeps, for each card message Messenger sends, its fingerprint and the names
 * of its transports.
 */
final class SentCards
{
    /** @var list<array{string, list<string>}> */
    public array $sent = [];

    public function __invoke(SendMessageToTransportsEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();

        if ($message instanceof GenerateSocialCard) {
            $this->sent[] = [$message->fingerprint, array_keys($event->getSenders())];
        }
    }
}
