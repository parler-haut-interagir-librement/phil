<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Infrastructure\Messenger;

/**
 * Draw the card of this fingerprint: the message carries the fingerprint alone, and the handler reads what to draw
 * from the kept request.
 */
final readonly class GenerateSocialCard
{
    public function __construct(
        public string $fingerprint,
    ) {
    }
}
