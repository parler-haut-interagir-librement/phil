<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Infrastructure\Messenger;

use Phil\SocialCardsBundle\Application\SocialCards;

/**
 * Draws the card of a `GenerateSocialCard` message; a fingerprint no page named any more is dropped.
 */
final readonly class GenerateSocialCardHandler
{
    public function __construct(
        private SocialCards $cards,
    ) {
    }

    public function __invoke(GenerateSocialCard $message): void
    {
        $this->cards->generate($message->fingerprint);
    }
}
